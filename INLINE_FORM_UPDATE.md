# WeSarafi Inline Form Update

All Blade pages that previously used Bootstrap offcanvas forms now use in-page toggleable form panels.

Behavior:
- Add/Edit opens the form inside the module workspace.
- The list/table area is hidden while the form is open.
- Back to List restores the list without a page reload.
- Successful AJAX saves return to the list.
- Long forms scroll inside the workspace on desktop and naturally on mobile.
- Add after Edit resets hidden IDs, titles and key form state on affected modules.

Converted modules:
- HR: Employees, Shareholders
- Accounting: Expenses, Liabilities, Depreciation, Amortization
- Configuration: Permissions, Users, Branches, Roles, Designations, Liability Types, Expense Types, Income Sources, Currencies (Create/Edit)

Static verification:
- 15 toggle targets
- 16 form panels
- 0 missing targets
- 0 panels without Back to List
- 0 Bootstrap offcanvas calls/classes/attributes in Blade views
- 168 PHP source files linted with 0 syntax errors

Runtime note: run `npm ci && npm run build` and `php artisan test` on the target Mac/server.
