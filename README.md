# ITMOSOFT Laravel Vendor Commissions

Configurable vendor commission rules and a commission ledger for Laravel 12 and 13 marketplace applications.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- `itmosoft/laravel-vendors` when using the default vendor model (or pass a vendor model / ID)

## Installation

```bash
composer require itmosoft/laravel-vendor-commissions
php artisan vendor:publish --tag=itmosoft-vendor-commissions-config
php artisan migrate
```

The package creates `{table_prefix}vendor_commission_rules` and `{table_prefix}vendor_commissions`. The Eloquent models use the same configured prefix, so migrations and queries remain aligned. Configure `table_prefix` before migrating if you need different table names.

## Create commission rules

```php
use ItmoSoft\VendorCommissions\VendorCommissionManager;

$commissions = app(VendorCommissionManager::class);

// Global rule: 10% commission for USD orders.
$commissions->createRule([
    'name' => 'Default USD commission',
    'rate_type' => 'percentage',
    'rate' => '10.0000',
    'currency' => 'USD',
    'priority' => 0,
]);

// Vendor-specific rules take precedence over global rules.
$commissions->createRule([
    'name' => 'Preferred vendor rate',
    'vendor_id' => $vendor->getKey(),
    'rate_type' => 'percentage',
    'rate' => '7.5000',
    'currency' => 'USD',
    'priority' => 10,
]);
```

Rules also support `fixed` rates, `minimum_amount`, `maximum_amount`, `starts_at`, `ends_at`, `is_active`, and `priority`.

## Calculate and record a commission

```php
$calculation = $commissions->calculate($vendor, '125.00', 'USD');
// commission_amount: 12.50; vendor_amount: 112.50 (with a 10% rule)

$commission = $commissions->record($vendor, '125.00', 'USD', [
    'order_id' => $order->getKey(),
    'order_item_id' => $item->getKey(),
    'external_reference' => 'order-'.$order->getKey().'-item-'.$item->getKey(),
    'metadata' => ['channel' => 'web'],
]);
```

Use a stable `external_reference` to make event retries idempotent. Reusing a reference with a different vendor, amount, or currency throws a `RuntimeException`.

## Approve and reverse

```php
$commissions->approve($commission);
$commissions->reverse($commission, 'Order refunded');
$totals = $commissions->vendorTotals($vendor, 'USD');
```

Only pending commissions can be approved. A commission can be reversed once; reversal reason and timestamp are retained. Reversal does not delete the original record.

## Configuration

`config/itmosoft-vendor-commissions.php`:

- `table_prefix`: defaults to `itmosoft_`.
- `default_currency`: defaults to `USD`.
- `default_rate_type`: defaults to `percentage`.
- `default_rate`: defaults to `10.0000`.

## Important production notes

- Pass monetary amounts as decimal strings; avoid binary floating-point values in application code.
- Current schema stores money in `DECIMAL(18,2)` and rates in `DECIMAL(12,4)`. Adapt the migration and rounding policy for currencies with zero or three minor-unit digits.
- Commission calculation uses the first matching vendor-specific rule, otherwise the highest-priority global rule. If no rule matches, configured defaults are used.
- `minimum_amount` means the gross transaction must meet the threshold or the commission is zero. `maximum_amount` caps the commission amount.
- This package does not calculate taxes, refunds, chargebacks, payout eligibility, or transfer funds. Integrate those policies in the host application and coordinate reversals with the payout ledger.
- Protect rule management, approval, and reversal with host-application authorization. The package deliberately does not impose roles or policies.
- The commission record snapshots the selected rule's rate and calculated amounts so later rule edits do not rewrite historical records.
- Use database transactions and a stable `external_reference` for retry-safe event handling. For high-concurrency event ingestion, handle unique-key conflicts at the application boundary.

## Testing

```bash
composer validate --strict
composer install
composer test
```

## License

MIT. See [LICENSE](LICENSE).
