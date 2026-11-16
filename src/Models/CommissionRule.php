<?php

namespace ItmoSoft\VendorCommissions\Models;

use Illuminate\Database\Eloquent\Model;
use ItmoSoft\VendorCommissions\Enums\CommissionRateType;

class CommissionRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate_type' => CommissionRateType::class,
            'rate' => 'decimal:4',
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
