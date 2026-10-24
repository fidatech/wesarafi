# WeSarafi Final UI/UX + Stability Audit

Date: 2026-09-05

This release keeps the original WeSarafi visual design and workflows. Changes are limited to stability, usability, correctness, and performance.

## Fixed in this release

- Long module forms no longer become trapped inside `.fb-content`; desktop module workspaces have a reliable internal vertical scroll and tablet/mobile pages fall back to normal document scrolling.
- All module create/edit slide-over forms were converted to in-page toggleable forms. The list hides while the form is open, and each form has a Back to List action.
- Regular, Corporate, Temporary, Street Vendor, and Bank forms clear stale validation states and scroll/focus the first invalid field.
- Account form transitions no longer queue repeated jQuery animations.
- Customer AJAX submissions include a two-minute network timeout and clearer offline/server error messages.
- Corporate Customer creation now posts to the Corporate Customer endpoint.
- Bank creation now posts to the Bank endpoint.
- Bank categories are loaded from the database instead of hard-coded IDs 10/11.
- `is_active=0` now persists correctly; previous `Request::has()` logic made select-based records active even when Inactive was chosen.
- Customer/employee missing-image fallback is aligned to the existing `uploads/no_image.jpg` asset.
- DataTables use the supported `lengthChange: false` option instead of the invalid `length: false` option.
- Existing performance work remains: local frontend dependencies (no page-load CDNs), slim local Boxicons, query indexes, reduced duplicate middleware work, and centralized grouped-journal validation.

## Static verification completed

- 191 PHP files under app/routes/database/tests passed `php -l`.
- 216 route/controller action references checked; 0 missing controller methods.
- 0 duplicate literal HTML IDs found inside individual Blade files.
- 0 remote HTTP(S) script/link dependencies found in Blade views.
- JavaScript syntax check passed for `resources/js/app.js` and `vite.config.js`.

## Runtime boundary

Composer/vendor and node_modules are intentionally excluded from the source archive. This environment therefore could not run the Laravel integration test suite or Vite production build. Run the commands in README on the target Mac. A release should only be treated as production-ready after `php artisan test` and `npm run build` pass on that target environment.
