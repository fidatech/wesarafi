# WeSarafi institutional UI standardization — screenshot reference

Source of truth: user-provided **All Customers** screenshot (2026-10-26). Keep the existing top navigation and side menus unchanged. Apply consistent content headers, white rounded cards, pale-blue filter panels, labeled controls, outlined secondary actions, solid blue primary actions, inset bordered tables, compact pagination and centered empty states. Keep existing functionality, permissions, accessibility, translation/RTL and mobile overflow behavior. Never style PDF/export/print documents as interactive tables.

## Batches (report these after every completed batch)

- **UI-01** — Inventory, shared opt-in design classes, Remittances list pilot, tests and responsive QA.
- **UI-02** — Accounts: all/regular/corporate/temporary customers, street vendors, branch accounts and banks.
- **UI-03** — Exchanges, exchange rates, auction purchases and transaction audit views.
- **UI-04** — Remittances create/detail/approval and remaining operational workflows.
- **UI-05** — Journal entries, approval queues, ledgers and reconciliation.
- **UI-06** — Accounting: capital, assets, liabilities, expenses, depreciation and payments.
- **UI-07** — HR: employees, attendance, payroll and designations.
- **UI-08** — Configuration: users, roles, permissions, currencies, settings and audit logs.
- **UI-09** — Financial reports, monitoring and dashboard content; protect printable/regulatory formats.
- **UI-10** — Cross-page visual QA, responsive/RTL, keyboard/focus, dark-mode and regression tests.

## Current source inventory

| View area | Blade files | Files with table markup or table class |
|---|---:|---:|
| `accounting` | 15 | 9 |
| `accounts` | 12 | 7 |
| `auth` | 6 | 0 |
| `components` | 14 | 0 |
| `configuration` | 19 | 10 |
| `dashboard` | 1 | 0 |
| `dashboard.blade.php` | 1 | 1 |
| `exchange` | 4 | 4 |
| `financial_reports` | 16 | 13 |
| `hr` | 5 | 5 |
| `journal` | 5 | 4 |
| `layouts` | 18 | 1 |
| `monitoring` | 7 | 5 |
| `profile` | 4 | 0 |
| `remittances` | 6 | 3 |
| `welcome.blade.php` | 1 | 0 |

These counts describe files, not rendered routes. Shared partials may be reused across pages; route-level browser QA remains required.
