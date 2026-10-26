# WeSarafi Phase 1 — Migration table inventory

Generated from static `Schema::create` calls in repository migrations; dynamic package tables listed separately. This is a proposed classification, **not** a database schema change.

| Table | Proposed ownership | Migration |
|---|---|---|
| `account_sub_categories` | Tenant | `2025_06_21_142004_create_account_sub_categories_table.php` |
| `accounts` | Tenant | `2025_06_21_143201_create_accounts_table.php` |
| `amortizations` | Tenant | `2025_07_28_140156_create_amortizations_table.php` |
| `assets` | Tenant | `2025_07_28_100818_create_assets_table.php` |
| `attendances` | Tenant | `2026_07_26_000001_complete_operational_modules.php` |
| `auction_lines` | Tenant | `2025_08_10_202521_create_auction_lines_table.php` |
| `auctions` | Tenant | `2025_08_06_214858_create_auctions_table.php` |
| `branch_capital_transfers` | Tenant | `2026_09_14_000002_create_branch_capital_transfers_table.php` |
| `branches` | Tenant | `2025_06_19_164304_create_branches_table.php` |
| `business_days` | Tenant | `2026_10_25_020000_create_business_days_table.php` |
| `cache` | Infrastructure — review connection | `0001_01_01_000001_create_cache_table.php` |
| `cache_locks` | Infrastructure — review connection | `0001_01_01_000001_create_cache_table.php` |
| `capitals` | Tenant | `2025_06_27_173555_create_capitals_table.php` |
| `chart_of_accounts` | Tenant | `2025_06_21_144620_create_chart_of_accounts_table.php` |
| `currencies` | Special review | `2025_06_20_221709_create_currencies_table.php` |
| `customer_exchanges` | Tenant | `2026_07_26_000001_complete_operational_modules.php` |
| `depreciations` | Tenant | `2025_07_28_121545_create_depreciations_table.php` |
| `designations` | Tenant | `2025_06_21_010415_create_designations_table.php` |
| `employees` | Tenant | `2025_06_27_192017_create_employees_table.php` |
| `expense_payments` | Tenant | `2025_07_30_180949_create_expense_payments_table.php` |
| `expense_types` | Tenant | `2025_06_21_015835_create_expense_types_table.php` |
| `expenses` | Tenant | `2025_07_30_123437_create_expenses_table.php` |
| `failed_jobs` | Infrastructure — review connection | `0001_01_01_000002_create_jobs_table.php` |
| `fx_daily_valuation_lines` | Tenant | `2025_08_10_221049_create_fx_daily_valuation_lines_table.php` |
| `fx_daily_valuations` | Tenant | `2025_08_10_221029_create_fx_daily_valuations_table.php` |
| `fx_positions` | Tenant | `2025_08_10_222458_create_fx_positions_table.php` |
| `fx_rates` | Tenant | `2025_08_10_220943_create_fx_rates_table.php` |
| `fx_rate_updates` | Tenant | `2026_10_26_230000_create_fx_rate_updates_table.php`, `2026_10_27_000000_add_quote_metadata_to_fx_rate_updates.php` |
| `income_sources` | Tenant | `2025_06_21_022714_create_income_sources_table.php` |
| `job_batches` | Infrastructure — review connection | `0001_01_01_000002_create_jobs_table.php` |
| `jobs` | Infrastructure — review connection | `0001_01_01_000002_create_jobs_table.php` |
| `journal_reversals` | Tenant | `2026_10_25_040000_create_journal_reversals_table.php` |
| `liabilities` | Tenant | `2025_07_29_233414_create_liabilities_table.php` |
| `liability_payments` | Tenant | `2025_07_29_233441_create_liability_payments_table.php` |
| `liability_types` | Tenant | `2025_06_21_021258_create_liability_types_table.php` |
| `monitoring_reviews` | Tenant | `2026_09_14_000001_create_monitoring_reviews_table.php` |
| `nationalities` | Special review | `2025_06_21_142003_create_nationalities_table.php` |
| `password_reset_tokens` | Special review | `2025_06_19_164305_create_users_table.php` |
| `payroll_items` | Tenant | `2026_07_26_000001_complete_operational_modules.php` |
| `payrolls` | Tenant | `2026_07_26_000001_complete_operational_modules.php` |
| `provinces` | Special review | `2025_06_19_164303_create_province_table.php` |
| `remittance_legs` | Tenant | `2026_09_14_000003_add_remittance_routing_and_settlements.php` |
| `remittance_settlements` | Tenant | `2026_09_14_000003_add_remittance_routing_and_settlements.php` |
| `remittances` | Tenant | `2026_07_26_000001_complete_operational_modules.php` |
| `role_user` | Legacy/commented — verify | `2025_06_19_164306_create_permission_tables.php` |
| `sessions` | Special review | `2025_06_19_164305_create_users_table.php` |
| `settings` | Tenant | `2025_06_24_171403_create_settings_table.php` |
| `shareholders` | Tenant | `2025_06_27_173544_create_shareholders_table.php` |
| `transaction_types` | Tenant | `2025_06_21_144630_create_transaction_types_table.php` |
| `transactions` | Tenant | `2025_06_21_144637_create_transactions_table.php` |
| `users` | Special review | `2025_06_19_164305_create_users_table.php` |

## Dynamic package tables

Spatie Permission migration uses configured names for `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, and `role_has_permissions`; these belong to tenant permissions by default and require tenant-safe cache and guard configuration.

## Important exceptions

- `provinces`, `nationalities`, `currencies`: decide whether centrally versioned reference catalogs are copied into tenant databases; tenant financial foreign keys must remain local.
- `users`, `sessions`, `password_reset_tokens`: keep Sarafi staff identities tenant-scoped; separate central administrators and inspectors in independent identity stores.
- `cache`, `jobs` and failed-job tables: choose central or tenant infrastructure intentionally; never run a queued financial job without validated tenant context.
- `role_user`: migration source contains a commented `Schema::create`; do not treat as active without checking runtime schema.
- Tables introduced through package migrations (including activity logging) and `Schema::table` modifications require a separate migration and runtime inventory.

## Migration sequencing (design only)

1. Freeze schema baseline and take a verified, restorable copy in a disposable environment.
2. Create a separate central connection and registry; keep financial models on the current connection until isolation tests pass.
3. Configure tenant-specific migration paths, seeders, filesystem roots, cache prefixes and queue context.
4. Provision two disposable tenant databases; migrate and seed each independently.
5. Validate cross-tenant read/write isolation, role cache isolation, report exports and backups.
6. Reconcile all ledger totals and historical record counts before planning any live data migration.
7. Keep rollback as configuration/traffic reversal; do not destructively merge tenant financial databases.

## Phase 1 remaining work

- Inspect runtime schema and package migrations without exposing sensitive records.
- Inventory model relations and cross-connection query assumptions.
- Inspect scheduled tasks, backup/restore behavior and file paths for tenancy safety.
- Write an executable disposable-database isolation test plan.
