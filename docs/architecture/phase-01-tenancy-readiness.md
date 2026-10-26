# WeSarafi Phase 1 — Existing-System and Tenancy Readiness Audit

Status: architecture assessment in progress. This document describes repository evidence, not a completed tenancy migration or regulatory certification.

## Baseline (2026-10-26)

- Framework: Laravel 12.19.3; PHP requirement ^8.2.
- Current deployment model: single application/database; no `stancl/tenancy` dependency installed.
- Models: 38 PHP files under `app/Models`; Blade views: 134; test files: 44.
- Financial workflows already present: customer exchanges, remittances and settlement legs, general ledger transactions, journal approvals/reversals, business-day closing, branch cash reconciliation, FX positions/valuations, financial statements, monitoring and backup/restore.
- Packages already present: Spatie Permission, Spatie Activitylog, Laravel localization, Jalali, PhpSpreadsheet, Yajra DataTables.
- Existing branch access controls are not tenant boundaries. Branch IDs cannot substitute for tenant database isolation.

## Proposed ownership boundaries

### Central database (new; design only)
- Tenant registry, domains, provisioning state and tenant lifecycle.
- Subscription plans, invoices, renewals, entitlement status and license audit.
- Platform staff identities and permissions, customer support tickets, software versions and deployment health.
- Regulatory gateway client registrations, scopes, consent/legal authority records, access requests, delivery acknowledgements and audit metadata.
- Demo tenancy registration and expiry; no real customer financial data in demo.

### Tenant databases (existing operational domain)
- Branches, users, roles, permissions and employee access.
- Customers/accounts, customer exchanges, currencies, rates and remittances.
- Transactions, chart of accounts, financial statements and journals.
- Business days, reconciliation, treasury, FX positions and valuation.
- Financial and compliance evidence, supporting documents and regulatory report snapshots.
- Existing settings, shareholders, capital, payroll, expenses, assets and liabilities unless a later review establishes a central-only purpose.

### Special review required before migration
- `users`, `sessions`, `password_reset_tokens`: decide central platform authentication vs per-tenant identities; avoid shared user IDs without explicit mapping.
- Spatie permission tables and cache: tenant-scoped roles and permission cache keys.
- `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`: central/tenant queue and cache context, including retry isolation.
- `activity_log` and other audit history: preserve original actor identity and tenant provenance.
- `storage`, backups and exports: tenant-isolated paths, encryption, retention and restore boundaries.
- Seeder assumptions (e.g. branch and super-admin identities), global model scopes, raw DB queries, scheduled commands and report exports.
- Foreign keys, default connections and migration order; verify against disposable databases, never production.

## Regulatory gateway boundaries

- DAB inspection is a *proposal*, not an approved data-access integration.
- Build separate inspector authentication, authorization and audit trail; never reuse Fida subscription-admin privileges.
- No default bulk replication of tenant customer data to the central database.
- Provide approved report snapshots and narrowly scoped, logged API access only after legal/contractual authorization.
- Permit vendor-neutral reporting API integration in principle, subject to formal specifications.
- Regulatory report field mapping must be verified against the supplied quarterly Excel workbook and applicable DAB reporting instructions.

## Phase 1 exit criteria

1. Inventory every table and assign central, tenant or special-review ownership.
2. Identify cross-database references, jobs, auth, permissions, backups and data-leak risks.
3. Establish a reproducible green local test baseline.
4. Document a reversible, disposable-database tenancy migration sequence.
5. Approve architecture before installing Stancl or changing financial migrations.

## Implementation guardrails

- Work on a feature branch; preserve `main` and existing operational data.
- No production deployment, no destructive restore, no production schema changes.
- Do not promise DAB endorsement, inspector referrals or live access without written authorization.
