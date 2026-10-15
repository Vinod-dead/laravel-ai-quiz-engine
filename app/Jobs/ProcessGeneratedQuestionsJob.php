<?php

namespace App\Jobs;

use App\Enums\QuestionType;
use App\Models\Exam;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessGeneratedQuestionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, array<string, mixed>> $questions
     */
    public function __construct(
        public readonly int $examId,
        public readonly array $questions,
    ) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $exam = Exam::findOrFail($this->examId);

            $nextOrder = ((int) $exam->questions()->max('order')) + 1;

            $existingQuestions = $exam->questions()
                ->pluck('question')
                ->map(fn ($question) => $this->normalizeQuestion($question))
                ->all();

            foreach ($this->questions as $data) {
                if (! is_array($data)) {
                    continue;
                }

                $question = trim((string) ($data['question'] ?? ''));
                $answer = trim((string) ($data['correct_answer'] ?? ''));

                if ($question === '' || $answer === '') {
                    continue;
                }

                $type = QuestionType::tryFrom(
                    (string) ($data['type'] ?? '')
                ) ?? QuestionType::ShortAnswer;

                $options = $data['options'] ?? [];

                if (! is_array($options)) {
                    $options = [];
                }

                $options = array_values(array_unique(array_filter(
                    array_map(
                        fn ($option) => is_string($option) ? trim($option) : '',
                        $options
                    ),
                    fn ($option) => $option !== ''
                )));

                if ($type === QuestionType::MultipleChoice) {
                    if (count($options) < 2 || ! in_array($answer, $options, true)) {
                        continue;
                    }
                }

                if ($type === QuestionType::TrueFalse) {
                    $validAnswers = ['True', 'False'];

                    if (
                        count($options) !== 2 ||
                        ! in_array($options, [
                            ['True', 'False'],
                            ['False', 'True'],
                        ], true) ||
                        ! in_array($answer, $validAnswers, true)
                    ) {
                        continue;
                    }
                }

                $normalized = $this->normalizeQuestion($question);

                if ($normalized === '' || in_array($normalized, $existingQuestions, true)) {
                    continue;
                }

                $exam->questions()->create([
                    'question' => $question,
                    'type' => $type->value,
                    'options' => $type->hasOptions() ? $options : null,
                    'correct_answer' => $answer,
                    'order' => $nextOrder++,
                ]);

                $existingQuestions[] = $normalized;
            }
        });
    }

    private function normalizeQuestion(string $question): string
    {
        $question = mb_strtolower(trim($question));

        return preg_replace('/\s+/u', ' ', $question) ?? $question;
    }
}