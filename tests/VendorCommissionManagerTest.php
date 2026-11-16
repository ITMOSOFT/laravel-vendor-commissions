<?php

namespace ItmoSoft\VendorCommissions\Tests;

use InvalidArgumentException;
use ItmoSoft\VendorCommissions\Enums\CommissionStatus;
use ItmoSoft\VendorCommissions\VendorCommissionManager;
use RuntimeException;

class VendorCommissionManagerTest extends TestCase
{
    private function vendor(): TestVendor
    {
        return TestVendor::query()->create(['name' => 'Acme']);
    }

    public function test_it_uses_the_configured_default_percentage(): void
    {
        $result = app(VendorCommissionManager::class)->calculate($this->vendor(), '100.00');
        $this->assertSame('10.00', $result['commission_amount']);
        $this->assertSame('90.00', $result['vendor_amount']);
    }

    public function test_it_creates_and_applies_a_vendor_specific_percentage_rule(): void
    {
        $vendor = $this->vendor();
        $manager = app(VendorCommissionManager::class);
        $manager->createRule(['name' => 'Preferred vendor', 'vendor_id' => $vendor->id, 'rate_type' => 'percentage', 'rate' => '7.5000', 'currency' => 'USD', 'priority' => 1]);
        $result = $manager->calculate($vendor, '200.00');
        $this->assertSame('15.00', $result['commission_amount']);
        $this->assertSame('185.00', $result['vendor_amount']);
    }

    public function test_vendor_specific_rule_takes_precedence_over_global_rule(): void
    {
        $vendor = $this->vendor();
        $manager = app(VendorCommissionManager::class);
        $manager->createRule(['name' => 'Global', 'rate_type' => 'percentage', 'rate' => '5', 'currency' => 'USD']);
        $manager->createRule(['name' => 'Vendor', 'vendor_id' => $vendor->id, 'rate_type' => 'percentage', 'rate' => '8', 'currency' => 'USD']);
        $this->assertSame('8.00', $manager->calculate($vendor, '100')['commission_amount']);
    }

    public function test_it_supports_fixed_commission_rules(): void
    {
        $vendor = $this->vendor();
        $manager = app(VendorCommissionManager::class);
        $manager->createRule(['name' => 'Fixed', 'rate_type' => 'fixed', 'rate' => '3.25', 'currency' => 'USD']);
        $this->assertSame('3.25', $manager->calculate($vendor, '50')['commission_amount']);
    }

    public function test_commission_is_capped_at_the_gross_amount(): void
    {
        $vendor = $this->vendor();
        $manager = app(VendorCommissionManager::class);
        $manager->createRule(['name' => 'Large fixed', 'rate_type' => 'fixed', 'rate' => '500', 'currency' => 'USD']);
        $result = $manager->calculate($vendor, '20');
        $this->assertSame('20.00', $result['commission_amount']);
        $this->assertSame('0.00', $result['vendor_amount']);
    }

    public function test_it_records_commission_with_a_rule_snapshot(): void
    {
        $vendor = $this->vendor();
        $manager = app(VendorCommissionManager::class);
        $manager->createRule(['name' => 'Ten percent', 'rate_type' => 'percentage', 'rate' => '10', 'currency' => 'USD']);
        $commission = $manager->record($vendor, '125.00', 'USD', ['order_id' => 44, 'external_reference' => 'order-44-item-1']);
        $this->assertSame('12.50', $commission->commission_amount);
        $this->assertSame('112.50', $commission->vendor_amount);
        $this->assertSame(CommissionStatus::Pending, $commission->status);
        $this->assertNotNull($commission->commission_rule_id);
    }

    public function test_external_reference_makes_recording_idempotent(): void
    {
        $vendor = $this->vendor();
        $manager = app(VendorCommissionManager::class);
        $first = $manager->record($vendor, '100', 'USD', ['external_reference' => 'unique-1']);
        $second = $manager->record($vendor, '100', 'USD', ['external_reference' => 'unique-1']);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \ItmoSoft\VendorCommissions\Models\VendorCommission::query()->count());
    }

    public function test_external_reference_cannot_be_reused_for_different_amount(): void
    {
        $this->expectException(RuntimeException::class);
        $vendor = $this->vendor();
        $manager = app(VendorCommissionManager::class);
        $manager->record($vendor, '100', 'USD', ['external_reference' => 'unique-2']);
        $manager->record($vendor, '101', 'USD', ['external_reference' => 'unique-2']);
    }

    public function test_it_approves_pending_commissions(): void
    {
        $manager = app(VendorCommissionManager::class);
        $commission = $manager->record($this->vendor(), '100');
        $this->assertSame(CommissionStatus::Approved, $manager->approve($commission)->status);
    }

    public function test_it_reverses_commissions_and_stores_the_reason(): void
    {
        $manager = app(VendorCommissionManager::class);
        $commission = $manager->record($this->vendor(), '100');
        $reversed = $manager->reverse($commission, 'Order refunded');
        $this->assertSame(CommissionStatus::Reversed, $reversed->status);
        $this->assertSame('Order refunded', $reversed->metadata['reversal_reason']);
        $this->assertNotNull($reversed->reversed_at);
    }

    public function test_it_rejects_approval_of_non_pending_commission(): void
    {
        $this->expectException(RuntimeException::class);
        $manager = app(VendorCommissionManager::class);
        $commission = $manager->record($this->vendor(), '100');
        $manager->approve($commission);
        $manager->approve($commission);
    }

    public function test_it_rejects_non_positive_gross_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(VendorCommissionManager::class)->calculate($this->vendor(), '0');
    }

    public function test_it_rejects_invalid_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(VendorCommissionManager::class)->calculate($this->vendor(), '10', 'US');
    }

    public function test_vendor_totals_exclude_reversed_commission_from_net_totals(): void
    {
        $vendor = $this->vendor();
        $manager = app(VendorCommissionManager::class);
        $commission = $manager->record($vendor, '100');
        $manager->record($vendor, '50');
        $manager->reverse($commission, 'Refund');
        $totals = $manager->vendorTotals($vendor);
        $this->assertSame('5.00', $totals['USD']['commission_amount']);
        $this->assertSame('45.00', $totals['USD']['vendor_amount']);
        $this->assertSame(2, $totals['USD']['count']);
    }
}
