<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationBatch extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'topic',
        'question_type',
        'question_count',
        'difficulty',
        'status',
        'questions',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'question_count' => 'integer',
            'difficulty' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}