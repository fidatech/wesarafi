# Phase 1 — Scoped runtime schema verification

Verified on 2026-10-26 against the configured **local MySQL `wesarafi` schema only**. Read-only `information_schema` queries were filtered with `TABLE_SCHEMA = DATABASE()` and did not inspect customer records or change data.

| Metric | Observed |
|---|---:|
| MySQL version | 8.0.33 |
| Base tables | 56 |
| Foreign-key column references | 110 |
| Index entries (`information_schema.STATISTICS`) | 269 |

`STATISTICS` counts index-column entries, not necessarily 269 distinct indexes. Foreign-key count likewise represents referenced columns, not necessarily 110 separate constraints.

## Runtime package tables confirmed

- `activity_log` (Spatie Activitylog)
- `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` (Spatie Permission)
- `migrations` (Laravel)

All are present in the local runtime schema and were not completely represented by static literal `Schema::create` inventory. The commented legacy `role_user` is **not** a runtime table.

## Ownership and foreign-key implications

All operational and package tables currently reside in a **single local schema**. In the hosted tenancy model, the operational schema and its relationships must be reproduced *within each tenant database*. Do not move shared-looking `currencies`, `nationalities` or `provinces` into a central-only database without retaining tenant-local keys: e.g., `accounts` references `nationalities`, `branches` and `users`.

Keep Spatie authorization tables, `activity_log`, and staff `users` tenant-scoped. Separate central identities and regulator authentication must use their own connections and guards. Laravel queue/cache/session infrastructure needs an explicit deployment decision before switching the default connection.

## Reproduction (metadata only)

Run SQL against the configured application connection with `TABLE_SCHEMA = DATABASE()`:

```sql
SELECT COUNT(*) FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';
SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL;
SELECT COUNT(*) FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE();
```

## Outstanding checks

1. Enumerate FK constraints and confirm all referenced schemas remain tenant-local on disposable databases.
2. Check indexes and composite unique constraints for tenant-specific assumptions.
3. Verify package permissions/cache isolation and queued jobs with two disposable MySQL tenants.
4. Validate file and backup paths, and preserve journal/remittance totals across disposable migration copies.
5. Do not run tenancy migrations or destructive restore against the current local financial data.
