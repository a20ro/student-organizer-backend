<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $fillable = [
        'type',
        'level',
        'message',
        'context',
        'user_id',
        'admin_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * Get the admin user who performed the action
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
