<?php

namespace App\Jobs;

use App\Models\AiGenerationBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use App\Services\QuestionSimilarityService;

class GenerateAiQuestionsBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(
        public readonly int $batchId,
    ) {}

   public function handle(
    QuestionSimilarityService $similarityService
): void
    {
        $batch = AiGenerationBatch::find($this->batchId);

        if (! $batch || $batch->status === 'completed') {
            return;
        }

        $batch->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            $count = (int) $batch->question_count;

            if ($count < 1 || $count > 20) {
                throw new RuntimeException(
                    'Question count must be between 1 and 20.'
                );
            }

            $type = (string) $batch->question_type;
            $difficulty = (int) $batch->difficulty;

            $optionsInstruction = str_contains(
                strtolower($type),
                'short'
            )
                ? 'For each question, options must be an empty array.'
                : 'For each question, provide exactly 4 distinct options. '
                    .'For True/False, use ["True", "False"].';

            $prompt = <<<PROMPT
Generate exactly {$count} exam questions.

Topic: {$batch->topic}
Question type: {$type}
Difficulty: {$difficulty} on a scale of 1 to 5.

Return one valid JSON object with a "questions" array.
Every question must contain these fields:
- question: string
- type: string
- options: array of strings
- correct_answer: string
- explanation: short string
- difficulty: integer {$difficulty}

{$optionsInstruction}

For questions with options, correct_answer must match one option exactly.
Keep explanations concise. Do not include Markdown or text outside JSON.
PROMPT;

            $response = Http::timeout(210)
                ->acceptJson()
                ->post('http://localhost:11434/api/chat', [
                    'model' => 'llama3.2:latest',
                    'stream' => false,
                    'format' => 'json',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You generate exam questions. '
                                .'Return only valid JSON matching the requested structure.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'options' => [
                        'temperature' => 0.2,
                        'num_predict' => min(4096, max(1000, $count * 450)),
                    ],
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Ollama returned HTTP '.$response->status().'.'
                );
            }

            $content = $response->json('message.content');

            if (! is_string($content) || trim($content) === '') {
                throw new RuntimeException(
                    'Ollama returned an empty response.'
                );
            }

            $data = json_decode($content, true);

            if (
                json_last_error() !== JSON_ERROR_NONE
                || ! is_array($data)
                || ! isset($data['questions'])
                || ! is_array($data['questions'])
            ) {
                throw new RuntimeException(
                    'Ollama did not return valid JSON containing a questions array: '
                    .json_last_error_msg()
                );
            }

            $questions = [];

            foreach ($data['questions'] as $question) {
                if (
                    ! is_array($question)
                    || ! is_string($question['question'] ?? null)
                    || trim($question['question']) === ''
                    || ! is_string($question['type'] ?? null)
                    || ! is_array($question['options'] ?? null)
                    || ! is_string($question['correct_answer'] ?? null)
                    || trim($question['correct_answer']) === ''
                    || ! is_string($question['explanation'] ?? null)
                    || ! is_numeric($question['difficulty'] ?? null)
                ) {
                    continue;
                }

                $question['difficulty'] = (int) $question['difficulty'];

                if (
                    $question['difficulty'] < 1
                    || $question['difficulty'] > 5
                ) {
                    continue;
                }

                if (
                    count(array_filter(
                        $question['options'],
                        fn ($option) => is_string($option)
                    )) !== count($question['options'])
                ) {
                    continue;
                }

                $questions[] = $question;
            }
// Check for repeated questions within the generated batch.
$seenQuestions = [];

foreach ($questions as $question) {
    $normalized = mb_strtolower(
        (string) preg_replace(
            '/\s+/',
            ' ',
            trim($question['question'])
        )
    );

    if (isset($seenQuestions[$normalized])) {
        throw new RuntimeException(
            'Duplicate question detected within the generated batch. '
            .'Please retry generation.'
        );
    }

    $seenQuestions[$normalized] = true;
}

// Check for semantically similar questions already in the database.
foreach ($questions as $question) {
    $similar = $similarityService->findSimilar(
        query: $question['question'],
        limit: 1,
        minSimilarity: 0.85,
    );

    if ($similar->isNotEmpty()) {
        throw new RuntimeException(
            'A generated question is too similar to an existing question. '
            .'Please retry generation.'
        );
    }
}

// Keep the existing count validation below this point.
if (count($questions) !== $count) {
    throw new RuntimeException(
        'Received '.count($questions)
        .' valid questions; expected '.$count.'. '
        .'Please retry the batch.'
    );
}
            if (count($questions) !== $count) {
                throw new RuntimeException(
                    'Received '.count($questions)
                    .' valid questions; expected '.$count.'. '
                    .'Please retry the batch.'
                );
            }

            $batch->update([
                'status' => 'completed',
                'questions' => $questions,
                'error_message' => null,
            ]);

            Log::info('AI question batch completed.', [
                'batch_id' => $batch->id,
                'question_count' => count($questions),
            ]);
        } catch (Throwable $exception) {
            Log::error('AI question batch generation failed.', [
                'batch_id' => $this->batchId,
                'exception' => $exception->getMessage(),
            ]);

            $batch->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
