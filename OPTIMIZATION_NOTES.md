# WeSarafi Optimization Notes — 2026-09-05

## UI / UX fixes
- Preserved the original Sneat/WeSarafi visual design and menu hierarchy.
- Removed duplicate remote CSS/JS sources that made screens sensitive to weak Internet.
- Fixed page-script ordering so local jQuery/Bootstrap/DataTables/Select2/Flatpickr are ready before page code executes.
- Fixed missing/broken icon names in navbar and auction navigation.
- Added the Boxicons sizing/spinner utilities used by the original templates.
- Fixed broken user/customer/shareholder placeholder image references.
- Fixed shareholder DataTable profile-picture URL interpolation.
- Added non-invasive focus-visible, mobile action wrapping, responsive DataTable/modal and reduced-motion behavior.
- Kept RTL support and original form/table structure.

## Performance fixes
- Removed a duplicated `StartSession` middleware invocation.
- Slimmed the globally loaded icon CSS from roughly 1.28 MB source to roughly 84 KB source before compression.
- Removed all external page-load CDN dependencies from Blade views.
- Added targeted database indexes for transaction balances/approvals/monitoring, accounts, customer exchanges, remittances and employees.
- Memoized repeated effective FX-rate, currency-code and COA lookups inside a request.
- Removed stale compiled assets from the source release so deployment builds from the locked package versions.

## Accounting reliability
- Centralized grouped journal validation/posting in `TransactionService::postMany()`.
- `OperationalLedgerService` now delegates to the same engine.
- Grouped journals are rejected before insert when AFN debit and credit totals differ by more than AFN 0.02.
- Grouped posting is atomic and uses one `linked_id`.
- Fixed a migration rollback typo where the province migration attempted to drop `branches`.

## Verification boundary
Static source checks are included in `SOURCE_VERIFICATION.txt`. The final Laravel/PHPUnit and Vite runtime commands must be run on the target Mac/server because this build environment could not complete Composer/npm network dependency installation.
