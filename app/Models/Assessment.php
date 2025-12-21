<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'type',
        'grade_received',
        'grade_max',
        'due_date',
        'weight_percentage',
        'status',
        'google_event_id',
    ];

    protected $casts = [
        'grade_received' => 'decimal:2',
        'grade_max' => 'decimal:2',
        'weight_percentage' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function fileAttachments(): MorphMany
    {
        return $this->morphMany(FileAttachment::class, 'attachable');
    }
}

