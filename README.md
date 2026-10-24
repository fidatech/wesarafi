# WeSarafi — Optimized Original Edition

This release keeps the original WeSarafi design, navigation, terminology and business workflows, while improving accounting consistency, frontend reliability, database performance and low-bandwidth behavior.

## Requirements

- PHP 8.2+ (PHP 8.4 is supported by the locked dependencies)
- Composer 2
- MySQL 8 / MariaDB 10.6+
- Node.js 20+ and npm

## Fresh local installation

```bash
cd /Users/mastaan/Code/wesarafi
cp .env.example .env
composer install
php artisan key:generate
```

Create a MySQL database named `wesarafi`, then edit `.env`:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wesarafi
DB_USERNAME=root
DB_PASSWORD=
```

Prepare Laravel runtime directories and install the database:

```bash
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs
chmod -R 775 storage bootstrap/cache

php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan storage:link
```

Build the locked frontend dependencies:

```bash
npm ci
npm run build
```

Run tests, then start the application:

```bash
php artisan test
php artisan serve
```

Open: `http://127.0.0.1:8000`

Administrator bootstrap accounts are only created when `WESARAFI_SUPERADMIN_EMAIL` and `WESARAFI_SUPERADMIN_PASSWORD` (or the corresponding `WESARAFI_ADMIN_*` values) are explicitly provided in the private deployment environment. Passwords must be at least 16 characters. Do not commit credentials to Git. Existing accounts are not reset by the seeder. Rotate any previously used default passwords immediately.

## Upgrade an existing WeSarafi installation

**Do not run `migrate:fresh` on a client database.** It deletes all tables.

1. Back up the database and uploaded documents.
2. Deploy the new source while preserving the client's `.env` and upload/storage data.
3. Run:

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan view:cache
```

If `public/storage` is not already linked:

```bash
php artisan storage:link
```

## Performance changes in this edition

- Removed duplicate Laravel session middleware execution.
- Removed runtime dependencies on Google Fonts, jQuery CDN, DataTables CDN, Select2 CDN, Flatpickr CDN, Bootstrap CDN and other public asset CDNs.
- All normal UI libraries are served from the locally compiled Vite bundle.
- Replaced the ~1.3 MB global Iconify/Boxicons payload with a small WeSarafi-specific icon subset.
- Page scripts wait for the local application bundle, eliminating slow-network race conditions where `$`, DataTables, Select2 or Bootstrap were not ready.
- Added database indexes for high-frequency branch/date/account/approval/customer/remittance lookups.
- Added request-local FX rate/currency/COA caches to reduce repeated database queries.
- Preserved HTTP compression and long-lived static-asset caching already supported by the application/public server configuration.
- Replaced the transaction date-range CDN dependency with bundled Flatpickr.

## Accounting changes

`TransactionService` is now the authoritative validation/posting path for grouped accounting entries. `OperationalLedgerService` delegates to it instead of maintaining a separate posting implementation.

For grouped journals the engine now validates:

- required branch, COA, currency, amount and direction fields;
- positive native and AFN-equivalent amounts;
- one shared `linked_id`;
- atomic posting inside a database transaction;
- total AFN debits equal total AFN credits before any row is inserted;
- consistent approval/suspicious metadata.

The legacy single-line `TransactionService::post()` remains available for existing modules so this release does not unexpectedly break old workflows. New accounting work should prefer balanced `postMany()` groups.

## Tests added

`tests/Feature/Accounting/TransactionPostingTest.php` covers:

- balanced capital-style journal groups;
- rejection/rollback of unbalanced grouped journals;
- legacy `OperationalLedgerService` delegation to the central posting engine.

Always run the complete suite after configuring the test database:

```bash
php artisan test
```

## Low-bandwidth deployment notes

For branches on weak Internet connections:

- Keep `APP_DEBUG=false` in production.
- Build assets once during deployment; never run Vite development mode on production.
- Enable HTTPS and HTTP/2 or HTTP/3 through the web server/CDN if available.
- Keep gzip/Brotli enabled at the web-server layer.
- Do not reintroduce public CDN JavaScript/font dependencies; local assets are intentional.
- Use PHP OPcache in production.
- Use Redis for cache/sessions only when the server is configured reliably; file cache/session defaults remain deployment-friendly.

## Important safety rule

Before upgrading any live sarafi installation, make a verified database + uploaded-files backup. Accounting migrations should use `php artisan migrate --force`; never use `migrate:fresh` on live data.

## Backup and restore security

- Backup download is restricted to Super Admin and currently supports MySQL via `mysqldump` with a password environment variable, not a password command-line argument. Verify that the binary exists on the host and test recovery before relying on backups.
- Web-based database restoration is deliberately disabled (HTTP 503). Restore only during a planned maintenance window using a verified, separately retained snapshot and an offline administrator procedure.
- SQLite online backups are disabled until a transactionally consistent snapshot method is implemented.
- A database-only backup does not include uploaded documents, encryption keys, or the application environment. Back those up separately and securely.
- On October 24, 2026, the development checkout passed 71 automated tests (608 assertions) at commit `1f2171e`. Subsequent commits require a fresh test run. This is not staging or disaster-recovery verification; do not deploy or merge without those checks.
