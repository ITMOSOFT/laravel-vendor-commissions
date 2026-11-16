<?php

namespace ItmoSoft\VendorCommissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use ItmoSoft\VendorCommissions\Enums\CommissionRateType;
use ItmoSoft\VendorCommissions\Enums\CommissionStatus;
use ItmoSoft\VendorCommissions\Models\CommissionRule;
use ItmoSoft\VendorCommissions\Models\VendorCommission;
use RuntimeException;

class VendorCommissionManager
{
    public function createRule(array $attributes): CommissionRule
    {
        $data = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:160'],
            'vendor_id' => ['nullable', 'integer'],
            'rate_type' => ['required', 'in:percentage,fixed'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'minimum_amount' => ['nullable', 'numeric', 'gte:0'],
            'maximum_amount' => ['nullable', 'numeric', 'gt:0'],
            'priority' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ])->validate();

        $data['currency'] = strtoupper($data['currency']);
        $data['priority'] ??= 0;
        $data['is_active'] ??= true;

        return CommissionRule::query()->create($data);
    }

    /** @return array{rule: ?CommissionRule, gross_amount: string, commission_amount: string, vendor_amount: string, currency: string} */
    public function calculate(Model|int $vendor, string|int|float $grossAmount, ?string $currency = null): array
    {
        $vendorId = $vendor instanceof Model ? $vendor->getKey() : $vendor;
        $currency = strtoupper($currency ?? config('itmosoft-vendor-commissions.default_currency', 'USD'));
        $gross = $this->money($grossAmount);

        if ($gross <= 0) {
            throw new InvalidArgumentException('Gross amount must be greater than zero.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be a valid three-letter code.');
        }

        $now = now();
        $rule = CommissionRule::query()
            ->where('is_active', true)
            ->where('currency', $currency)
            ->where(function ($query) use ($vendorId): void {
                $query->whereNull('vendor_id')->orWhere('vendor_id', $vendorId);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderByRaw('CASE WHEN vendor_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('priority')
            ->first();

        $type = $rule?->rate_type ?? CommissionRateType::from(config('itmosoft-vendor-commissions.default_rate_type', 'percentage'));
        $rate = (string) ($rule?->rate ?? config('itmosoft-vendor-commissions.default_rate', '10.0000'));
        $commission = $type === CommissionRateType::Percentage
            ? round($gross * ((float) $rate / 100), 2)
            : $this->money($rate);

        if ($rule?->minimum_amount !== null && $gross < (float) $rule->minimum_amount) {
            $commission = 0.0;
        }
        if ($rule?->maximum_amount !== null) {
            $commission = min($commission, (float) $rule->maximum_amount);
        }

        $commission = min($gross, max(0, round($commission, 2)));

        return [
            'rule' => $rule,
            'gross_amount' => number_format($gross, 2, '.', ''),
            'commission_amount' => number_format($commission, 2, '.', ''),
            'vendor_amount' => number_format($gross - $commission, 2, '.', ''),
            'currency' => $currency,
        ];
    }

    public function record(Model|int $vendor, string|int|float $grossAmount, ?string $currency = null, array $attributes = []): VendorCommission
    {
        $vendorId = $vendor instanceof Model ? $vendor->getKey() : $vendor;
        $calculation = $this->calculate($vendorId, $grossAmount, $currency);

        return DB::transaction(function () use ($vendorId, $calculation, $attributes): VendorCommission {
            if (! empty($attributes['external_reference'])) {
                $existing = VendorCommission::query()->where('external_reference', $attributes['external_reference'])->first();
                if ($existing) {
                    if ((int) $existing->vendor_id !== (int) $vendorId || $existing->gross_amount !== $calculation['gross_amount'] || $existing->currency !== $calculation['currency']) {
                        throw new RuntimeException('External reference has already been used for a different commission.');
                    }
                    return $existing;
                }
            }

            $rule = $calculation['rule'];
            return VendorCommission::query()->create([
                'vendor_id' => $vendorId,
                'commission_rule_id' => $rule?->getKey(),
                'order_id' => $attributes['order_id'] ?? null,
                'order_item_id' => $attributes['order_item_id'] ?? null,
                'source_type' => $attributes['source_type'] ?? null,
                'source_id' => $attributes['source_id'] ?? null,
                'external_reference' => $attributes['external_reference'] ?? null,
                'gross_amount' => $calculation['gross_amount'],
                'commission_amount' => $calculation['commission_amount'],
                'vendor_amount' => $calculation['vendor_amount'],
                'currency' => $calculation['currency'],
                'rate_type' => $rule?->rate_type ?? CommissionRateType::from(config('itmosoft-vendor-commissions.default_rate_type', 'percentage')),
                'rate' => $rule?->rate ?? config('itmosoft-vendor-commissions.default_rate', '10.0000'),
                'status' => CommissionStatus::Pending,
                'metadata' => $attributes['metadata'] ?? [],
            ]);
        });
    }

    public function approve(VendorCommission $commission): VendorCommission
    {
        if ($commission->status !== CommissionStatus::Pending) {
            throw new RuntimeException('Only pending commissions can be approved.');
        }
        $commission->forceFill(['status' => CommissionStatus::Approved])->save();
        return $commission->refresh();
    }

    public function reverse(VendorCommission $commission, ?string $reason = null): VendorCommission
    {
        if ($commission->status === CommissionStatus::Reversed) {
            throw new RuntimeException('Commission has already been reversed.');
        }
        $metadata = $commission->metadata ?? [];
        if ($reason !== null) {
            $metadata['reversal_reason'] = $reason;
        }
        $commission->forceFill([
            'status' => CommissionStatus::Reversed,
            'reversed_at' => now(),
            'metadata' => $metadata,
        ])->save();
        return $commission->refresh();
    }

    public function vendorTotals(Model|int $vendor, ?string $currency = null): array
    {
        $vendorId = $vendor instanceof Model ? $vendor->getKey() : $vendor;
        $query = VendorCommission::query()->where('vendor_id', $vendorId);
        if ($currency !== null) {
            $query->where('currency', strtoupper($currency));
        }
        $rows = $query->get()->groupBy('currency');
        $totals = [];
        foreach ($rows as $code => $commissions) {
            $totals[$code] = [
                'gross_amount' => number_format((float) $commissions->sum('gross_amount'), 2, '.', ''),
                'commission_amount' => number_format((float) $commissions->filter(fn (VendorCommission $commission): bool => $commission->status !== CommissionStatus::Reversed)->sum('commission_amount'), 2, '.', ''),
                'vendor_amount' => number_format((float) $commissions->filter(fn (VendorCommission $commission): bool => $commission->status !== CommissionStatus::Reversed)->sum('vendor_amount'), 2, '.', ''),
                'count' => $commissions->count(),
            ];
        }
        return $totals;
    }

    private function money(string|int|float $amount): float
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be numeric.');
        }
        return round((float) $amount, 2);
    }
}
