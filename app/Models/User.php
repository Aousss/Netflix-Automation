<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = [
        'remember_token',
        'netflix_profile_pin',
    ];

    protected function casts(): array
    {
        return [
            'netflix_profile_pin' => 'encrypted',
            'monthly_share_amount' => 'decimal:2',
            'credit_balance' => 'decimal:2',
            'grace_bypass_used_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasActiveGraceBypass(): bool
    {
        return $this->grace_bypass_used_at !== null
            && $this->grace_bypass_used_at->gt(now()->subHours(24));
    }

    public function canAccessVault(?Payment $currentPayment): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $currentPayment) {
            return true;
        }

        if ($currentPayment->status === 'overdue') {
            return $this->hasActiveGraceBypass();
        }

        return true;
    }
}
