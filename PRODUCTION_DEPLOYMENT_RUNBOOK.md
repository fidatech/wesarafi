# WeSarafi — Production deployment and recovery runbook

Status: preparation only. This document is not proof that a production backup or restore rehearsal succeeded.

## Preflight gates

1. Identify the target host, deployed commit, PHP/MySQL versions, available disk space, database size, maintenance window, and authorized operator.
2. Confirm the target database is production, and **never** run `migrate:fresh`, `migrate:refresh`, `migrate:reset`, or `migrate:rollback` against financial records.
3. Verify `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, private `.env`, and preservation of `APP_KEY`. Do not display or copy secrets into logs.
4. Capture an encrypted off-host backup of the database and uploaded documents, plus separately protected configuration/key material. Record hashes, timestamp, and retention location.
5. Rehearse database restoration in a **separate empty database** on an isolated environment. Verify representative row counts, migration state, logins/permissions, branch isolation, journal balances, reports, and document attachments. Do not consider a successful ZIP extraction proof of recovery.
6. Confirm restore commands' MySQL tooling is installed and the isolated account has only the required permissions.
7. Verify a tested rollback path and operator approval before any production change.

## Read-only checks on target

```bash
git rev-parse --short HEAD
php -v
php artisan about
php artisan migrate:status
php artisan route:list --path=financial-reports
```

Do not paste command output containing credentials, private customer information, or financial records into public tickets.

## Planned deployment (only after preflight gates)

1. Stop incoming writes and background workers; enter maintenance mode, and confirm jobs cannot post journals during migration.
2. Take a final, verified, off-host database and uploaded-files snapshot.
3. Deploy the reviewed Git commit, preserving `.env`, `APP_KEY`, and storage.
4. Install locked Composer dependencies and build the Vite assets in a controlled environment.
5. Review pending migrations and execute `php artisan migrate --force` **only with operator approval**.
6. Rebuild configuration/view caches, restart queues/workers, and verify storage links and permissions.
7. Smoke-test authentication, role restrictions, branch scoping, posting and reversal controls, trial balance, audit dashboard, and responsive financial tables using safe test data.
8. Reopen traffic only after financial reconciliation and operator sign-off.

## Rollback and incident handling

- If deployment fails **before** migrations or writes, restore the previous application release and caches.
- If schema changes or new financial writes occurred, **do not** assume a Git revert or migration rollback can safely recover data. Keep maintenance mode on, reconcile the change window, and follow the rehearsed database-and-files restoration procedure with financial owner approval.
- Never run `backup:restore-current` on the active database without a separately retained backup, a successful isolated rehearsal, maintenance mode, and explicit authorization.
- Record incident timestamps, affected branches, transaction counts, database snapshot identifiers, and post-recovery ledger reconciliation.

## Evidence still required

- [ ] Production host and deployment path confirmed
- [ ] Production database backup hash and off-host retention verified
- [ ] Uploaded documents and key/configuration recovery verified
- [ ] Isolated restore rehearsal passed
- [ ] Production pending migration review completed
- [ ] Maintenance window and rollback owner approved
- [ ] Post-deployment financial reconciliation passed
