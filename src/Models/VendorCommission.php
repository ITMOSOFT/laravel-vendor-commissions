<?php

namespace ItmoSoft\VendorCommissions\Models;

use Illuminate\Database\Eloquent\Model;
use ItmoSoft\VendorCommissions\Enums\CommissionRateType;
use ItmoSoft\VendorCommissions\Enums\CommissionStatus;

class VendorCommission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate_type' => CommissionRateType::class,
            'rate' => 'decimal:4',
            'gross_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'vendor_amount' => 'decimal:2',
            'status' => CommissionStatus::class,
            'metadata' => 'array',
            'reversed_at' => 'datetime',
        ];
    }
}
