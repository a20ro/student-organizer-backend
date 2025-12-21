<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'major',
        'university',
        'role',
        'status',
        'last_login',
        'google_id',
        'google_email',
        'google_access_token',
        'google_refresh_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login' => 'datetime',
        ];
    }
    
    // Helper methods
    public function isAdmin(): bool
    {
        // Treat both admin and super_admin as admins
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
    
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // Relationships
    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class);
    }

    public function aiSessions()
    {
        return $this->hasMany(AiSession::class);
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    public function recurringTransactions()
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }
}
