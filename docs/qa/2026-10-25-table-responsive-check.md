# Local responsive table QA — 2026-10-25

Environment: authenticated local Chrome, `http://127.0.0.1:8000`; **no production access**.

Measured document `scrollWidth` against viewport `innerWidth` at 390px, 770px, and 1570px. All checked values matched (no document-level horizontal overflow):

| Page | 390px | 770px | 1570px |
| --- | --- | --- | --- |
| Configuration / Roles | Pass | Pass | Pass |
| Configuration / Branches | Pass | Pass | Pass |
| Configuration / Audit Logs | Pass | Pass | Pass |
| Accounts / All Customers | Pass | Pass | Pass |
| Financial Reports / Remittances | Pass | Pass | Pass |

These are geometry checks only. They do not prove accessibility, successful AJAX responses, pagination correctness, or action authorization. No financial mutations were performed. Chrome was returned to desktop dimensions.
