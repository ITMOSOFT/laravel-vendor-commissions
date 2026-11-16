# Release instructions

## Repository

- Name: `laravel-vendor-commissions`
- Description: Configurable vendor commission rules and commission ledger for Laravel marketplace applications.
- URL: https://github.com/ITMOSOFT/laravel-vendor-commissions

## Validate before release

```bash
composer validate --strict
composer install
composer test
```

Do not tag or publish until the complete test suite passes on a supported PHP/Laravel matrix.

## Initial push

```bash
git init
git branch -M main
git add .
git commit -m "feat: add Laravel vendor commissions package"
git remote add origin https://github.com/ITMOSOFT/laravel-vendor-commissions.git
git push -u origin main
```

## Tag and push

```bash
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

## GitHub Release

- Title: `v1.0.0 - Initial Release`
- Tag: `v1.0.0`

Suggested description:

```markdown
Initial release of ITMOSOFT Laravel Vendor Commissions.

### Features
- Configurable global and vendor-specific commission rules.
- Percentage and fixed commission calculations.
- Rule priority, currency, effective dates, minimum transaction thresholds, and commission caps.
- Commission ledger with immutable calculation snapshots.
- Pending, approved, and reversed lifecycle.
- External-reference idempotency for retry-safe recording.
- Vendor commission totals by currency.

### Compatibility
- PHP 8.3+
- Laravel 12 and 13
```

After the GitHub release, submit the repository to Packagist: https://packagist.org/packages/submit.
