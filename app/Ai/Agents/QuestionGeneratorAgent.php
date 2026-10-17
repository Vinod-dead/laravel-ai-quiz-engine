<?php

namespace App\Ai\Agents;

use App\Enums\QuestionType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Model('llama3.2:latest')]
#[MaxTokens(3072)]
#[Temperature(0.2)]
#[Timeout(180)]
class QuestionGeneratorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private readonly string $topic,
        private readonly string $type,
        private readonly int $count,
        private readonly int $difficulty,
    ) {}

    public function instructions(): Stringable|string
    {
        $typeLabel = QuestionType::from($this->type)->label();

        $difficultyLabel = match ($this->difficulty) {
            1 => 'very easy (beginner)',
            2 => 'easy',
            3 => 'medium',
            4 => 'hard',
            5 => 'very hard (expert)',
            default => 'medium',
        };

        $optionsNote = QuestionType::from($this->type)->hasOptions()
            ? 'Provide exactly 4 distinct answer choices for each question. For True/False, use exactly ["True", "False"].'
            : 'For short-answer questions, use an empty options array.';

        return <<<INSTRUCTIONS
You are an exam-question generator.

Create exactly {$this->count} {$typeLabel} question(s).
Topic: {$this->topic}
Difficulty: {$difficultyLabel} (level {$this->difficulty}).

Return only one JSON object with a "questions" array.
Each question must contain:
- question: a clear question string
- type: a string
- options: an array of strings
- correct_answer: a string
- explanation: one short sentence
- difficulty: the integer {$this->difficulty}

{$optionsNote}

For multiple-choice and True/False questions, correct_answer must exactly match one option.
Keep questions and explanations concise. Do not use Markdown or text outside the JSON.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()
                ->items(
                    $schema->object([
                        'question' => $schema->string()->required(),
                        'type' => $schema->string()->required(),
                        'options' => $schema->array()
                            ->items($schema->string())
                            ->required(),
                        'correct_answer' => $schema->string()->required(),
                        'explanation' => $schema->string()->required(),
                        'difficulty' => $schema->integer()
                            ->min(1)
                            ->max(5)
                            ->required(),
                    ])
                )
                ->required(),
        ];
    }
}
