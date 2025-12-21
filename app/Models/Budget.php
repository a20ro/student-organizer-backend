<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'amount',
        'year',
        'month',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
