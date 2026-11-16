# Changelog

## [1.0.0] - 2026-11-16

- Initial release of configurable vendor commission rules.
- Support percentage and fixed commission calculations.
- Support global and vendor-specific rules, currency, priority, and effective dates.
- Record commission snapshots with pending, approved, and reversed states.
- Add external-reference idempotency and vendor totals.

## 1.0.1 - 2026-11-16

- Fixed Eloquent models to use the configured table prefix and the same table names created by the package migration.
- Updated the test setup to run package migrations explicitly against the in-memory testing connection and assert both tables exist.


## 1.0.2 - 2026-11-16

- Fixed GitHub Actions dependency matrix by passing `--prefer-lowest` and `--prefer-stable` to `composer update` rather than the install action.
- Kept PHP 8.3/8.4 and lowest/stable dependency coverage.
