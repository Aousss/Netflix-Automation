<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

