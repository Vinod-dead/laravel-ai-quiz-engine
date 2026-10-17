
<?php

use App\Enums\QuestionType;
use App\Jobs\GenerateAiQuestionsBatchJob;
use App\Jobs\ProcessGeneratedQuestionsJob;
use App\Models\AiGenerationBatch;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

it('queues AI generation and stores the batch as pending', function () {
    $teacher = User::factory()->teacher()->create();
    $exam = Exam::factory()->published()->for($teacher)->create();

    Bus::fake();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.exams.questions', ['exam' => $exam])
        ->set('aiTopic', 'Basic Math')
        ->set('aiType', QuestionType::MultipleChoice->value)
        ->set('aiCount', 1)
        ->set('aiDifficulty', 1)
        ->call('streamGenerateWithAi')
        ->assertSet('aiError', '')
        ->assertSet('aiGenerating', true)
        ->assertCount('pendingAiQuestions', 0);

    $batch = AiGenerationBatch::query()
        ->where('exam_id', $exam->id)
        ->where('user_id', $teacher->id)
        ->latest('id')
        ->firstOrFail();

    expect($batch->status)->toBe('pending');

    Bus::assertDispatched(
        GenerateAiQuestionsBatchJob::class,
        fn (GenerateAiQuestionsBatchJob $job) => $job->batchId === $batch->id
    );
});

it('does not add questions to the exam while generation is queued', function () {
    $teacher = User::factory()->teacher()->create();
    $exam = Exam::factory()->published()->for($teacher)->create();

    Bus::fake();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.exams.questions', ['exam' => $exam])
        ->set('aiTopic', 'Science')
        ->set('aiType', QuestionType::ShortAnswer->value)
        ->set('aiCount', 6)
        ->set('aiDifficulty', 2)
        ->call('streamGenerateWithAi')
        ->assertCount('pendingAiQuestions', 0)
        ->assertSet('aiGenerating', true);

    expect($exam->fresh()->questions)->toHaveCount(0);

    $batch = AiGenerationBatch::query()
        ->where('exam_id', $exam->id)
        ->where('user_id', $teacher->id)
        ->latest('id')
        ->firstOrFail();

    expect($batch->topic)->toBe('Science')
        ->and($batch->question_count)->toBe(6)
        ->and($batch->status)->toBe('pending');

    Bus::assertDispatched(
        GenerateAiQuestionsBatchJob::class,
        fn (GenerateAiQuestionsBatchJob $job) => $job->batchId === $batch->id
    );
});

it('can confirm a single pending AI question and save it to the exam', function () {
    $teacher = User::factory()->teacher()->create();
    $exam = Exam::factory()->published()->for($teacher)->create();

    $suggestedQuestion = [
        'question' => 'What is Laravel?',
        'type' => QuestionType::ShortAnswer->value,
        'options' => [],
        'correct_answer' => 'A PHP framework',
        'explanation' => 'Laravel is a PHP framework.',
        'difficulty' => 2,
    ];

    Livewire::actingAs($teacher)
        ->test('pages::teacher.exams.questions', ['exam' => $exam])
        ->set('pendingAiQuestions', [$suggestedQuestion])
        ->call('confirmAiQuestion', 0)
        ->assertCount('pendingAiQuestions', 0);

    expect($exam->fresh()->questions)->toHaveCount(1);
});

it('can confirm all pending AI questions and save them to the exam', function () {
    $teacher = User::factory()->teacher()->create();
    $exam = Exam::factory()->published()->for($teacher)->create();

    $suggestedQuestions = [
        [
            'question' => 'Q1?',
            'type' => QuestionType::ShortAnswer->value,
            'options' => [],
            'correct_answer' => 'A1',
            'explanation' => '',
            'difficulty' => 1,
        ],
        [
            'question' => 'Q2?',
            'type' => QuestionType::ShortAnswer->value,
            'options' => [],
            'correct_answer' => 'A2',
            'explanation' => '',
            'difficulty' => 1,
        ],
    ];

    Livewire::actingAs($teacher)
        ->test('pages::teacher.exams.questions', ['exam' => $exam])
        ->set('pendingAiQuestions', $suggestedQuestions)
        ->call('confirmAllAiQuestions')
        ->assertCount('pendingAiQuestions', 0);

    expect($exam->fresh()->questions)->toHaveCount(2);
});

it('ProcessGeneratedQuestionsJob creates questions for the exam', function () {
    $teacher = User::factory()->teacher()->create();
    $exam = Exam::factory()->for($teacher)->create();

    $questions = [
        [
            'question' => 'What is PHP?',
            'type' => QuestionType::ShortAnswer->value,
            'correct_answer' => 'A scripting language',
        ],
        [
            'question' => 'Pick one',
            'type' => QuestionType::MultipleChoice->value,
            'options' => ['A', 'B'],
            'correct_answer' => 'A',
        ],
    ];

    (new ProcessGeneratedQuestionsJob($exam->id, $questions))->handle();

    expect($exam->fresh()->questions)->toHaveCount(2);
});

it('ProcessGeneratedQuestionsJob skips incomplete question entries', function () {
    $teacher = User::factory()->teacher()->create();
    $exam = Exam::factory()->for($teacher)->create();

    $questions = [
        [
            'question' => 'Valid?',
            'type' => QuestionType::ShortAnswer->value,
            'correct_answer' => 'Yes',
        ],
        [
            'question' => 'Missing answer',
            'type' => QuestionType::ShortAnswer->value,
        ],
        [
            'type' => QuestionType::ShortAnswer->value,
            'correct_answer' => 'No question text',
        ],
    ];

    (new ProcessGeneratedQuestionsJob($exam->id, $questions))->handle();

    expect($exam->fresh()->questions)->toHaveCount(1);
});
