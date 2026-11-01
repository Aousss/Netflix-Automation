<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSetting extends Model
{
    protected $guarded = ['id'];

    protected $hidden = [
        'netflix_password',
    ];

    protected function casts(): array
    {
        return [
            'netflix_password' => 'encrypted',
            'password_updated_at' => 'datetime',
            'total_monthly_cost' => 'decimal:2',
            'billing_day_of_month' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::firstOrFail();
    }
}

