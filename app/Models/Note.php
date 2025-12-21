<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'content',
        'week_number',
        'attachments',
        'is_pinned',
        'is_favorite',
        'share_token',
        'is_public',
        'tags',
    ];

    protected $casts = [
        'attachments' => 'array',
        'tags' => 'array',
        'is_pinned' => 'boolean',
        'is_favorite' => 'boolean',
        'is_public' => 'boolean',
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

