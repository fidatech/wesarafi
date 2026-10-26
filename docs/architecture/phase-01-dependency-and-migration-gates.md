# Phase 1 — Dependency review and safe tenancy migration gates

Repository assessment dated 2026-10-26. **No tenancy package installation, database migration, or production action has been performed.**

## Verified dependency findings

1. **Eloquent relationships:** `Transaction` joins to branch, currency, account, chart of accounts, and tenant user; `Remittance` references branches, currencies, sender/receiver accounts and remittance legs; `BranchCapitalTransfer` references two branches. These entities must remain in the same tenant connection. Never treat branch IDs as global tenant identifiers.
2. **Model connection defaults:** No explicit `$connection` or `setConnection()` overrides were identified in `app/Models`. Stancl's default connection switching would affect all models unless central models are explicitly protected. This is a high-priority design gate.
3. **Authentication:** Existing `User` and Spatie roles/permissions belong to Sarafi operations. Introduce independent central admin and inspector identities, guards and sessions. Verify permission cache invalidation on tenant switches.
4. **Activity logging:** Published migrations create and modify the configured `activity_log` table through `config('activitylog.database_connection')`. Tenant audit logs must stay within their respective tenant connection; explicitly test configuration and actor resolution.
5. **Scheduler:** `routes/console.php` currently defines the default `inspire` command only. Existing artisan financial/backup commands are not tenant-aware. Audit all commands before introducing tenant-wide schedules.
6. **Files:** Customer exchange IDs use `Storage::disk('local')` with a legacy `public_path('uploads/id_cards')` fallback. Remittance uploads and user images use `Storage::disk('public')`. Tenant-specific private storage, secure download authorization and a legacy-file migration are required.
7. **Backup/restore:** BackupController and restore commands use shared `storage_path('app/backups/...')`; restore commands operate on the configured database. These commands must be disabled in tenant HTTP contexts until explicit target-tenant selection, isolated storage, least-privilege credentials and disposable restore tests are in place. **Never test a live restore on a real financial database.**
8. **Queries:** Raw aggregates in transaction and financial services rely on the active default connection. Ensure central controllers cannot accidentally run them against the central DB; ensure regulator endpoints use scoped tenant execution with strict authorization.
9. **Test harness:** `phpunit.xml` uses SQLite in-memory, array cache and session, sync queue. Existing tests do not establish MySQL multi-database isolation; separate MySQL integration tests are required.
10. **Schema inventory correction:** Static table inventory lists 50 declarations, including infrastructure and a commented legacy `role_user` declaration; Spatie Permission and Spatie Activitylog tables are dynamically named through configuration and must be included in runtime schema checks.

## Implementation gates (all required before enabling hosted tenants)

- **G1 — Baseline:** preserve existing 230 passing tests / 1,321 assertions and existing main branch. Add explicit two-tenant tests.
- **G2 — Connection model:** central DB contains tenant registry and billing only; tenant DB contains all Sarafi operational entities, staff and evidence; use explicit central models and a separate inspector service/guard.
- **G3 — Provisioning:** create and migrate two disposable MySQL databases; seed branch, currency, chart of accounts and role data independently; verify no foreign keys cross databases.
- **G4 — Isolation:** same numeric account/customer/user IDs in both tenants must never cross-resolve. Test read/write, authorization, roles cache, uploads, exports, backups, queued jobs, and session switching.
- **G5 — Integrity:** compare journal balance, remittance totals, customer and transaction counts, and closing snapshots before and after a **disposable copy** migration.
- **G6 — Recovery:** verify encrypted tenant backup and restore on isolated disposable databases, then test rollback by reversing routing/configuration, not merging records.
- **G7 — Regulatory:** no DAB access to real records before written authorization and reviewed API scopes, audit logging, retention, incident response and security assessment.

## Next implementation sequence

1. Inspect runtime schema and map package tables, indexes and foreign keys using non-sensitive metadata only.
2. Write an automated baseline test for table ownership and connection behavior, then add central/tenant model classification.
3. Install Stancl on this feature branch **after** a clean dependency compatibility review; do not migrate operational data in this step.
4. Build isolated central migrations and tenant migration directories, then provision disposable tenants.
5. Run the G1–G7 checks and document failures before merging.

## Remaining roadmap phases

02 Stancl multi-tenancy; 03 central Fida administration; 04 Sarafi daily operations; 05 finance and accounting controls; 06 DAB quarterly/annual reporting; 07 Regulatory Connect API; 08 DAB inspection portal; 09 AML/CFT; 10 public website/demo; 11 security/QA/deployment readiness.
