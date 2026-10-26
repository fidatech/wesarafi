import '../css/table-reference.css';
// jQuery
import $ from 'jquery';
window.$ = $;
window.jQuery = $;

// Bootstrap 5 JS (ES module)
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// DataTables (Bootstrap 5 styling)
import 'datatables.net-bs5';
import 'select2/dist/css/select2.min.css';
import select2 from 'select2';
select2($);
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
window.flatpickr = flatpickr;

// Alpine.js
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// Laravel & Sneat vendor scripts
import '../assets/vendor/js/helpers.js';
import '../assets/js/config.js';
import '../assets/vendor/js/menu.js';
import '../assets/js/main.js';

// Optional UI libraries
import '../assets/vendor/libs/popper/popper.js';
import '../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js';

// Authentication page helpers
import '../assets/js/pages-auth.js';

import Swal from 'sweetalert2';
window.Swal = Swal;

import './sidebar-toggle.js';

function initializeSelect2(root = document) {
    $(root).find('select.form-select:not(.no-select2)').each(function () {
        const $select = $(this);
        if ($select.hasClass('select2-hidden-accessible')) {
            // Edit forms may populate native values before opening the panel.
            $select.trigger('change.select2');
            return;
        }
        const $modal = $select.closest('.modal');
        const inInputGroup = $select.parent().hasClass('input-group');
        $select.select2({
            width: inInputGroup ? 'resolve' : '100%',
            dir: document.documentElement.dir || 'ltr',
            dropdownParent: $modal.length ? $modal : $(document.body),
            placeholder: $select.attr('placeholder') || undefined,
            allowClear: !$select.prop('required'),
        });
        if (inInputGroup) $select.parent().addClass('select2-input-group');
    });
}

window.initializeSelect2 = initializeSelect2;
$(function () { initializeSelect2(document); });
$(document).on('shown.bs.modal', '.modal', function () { initializeSelect2(this); });

// Native resets do not dispatch change events. Refresh after defaults are restored,
// without retriggering business handlers such as risk calculations or AJAX filters.
$(document).on('reset.wesSelect2', 'form', function () {
    const form = this;
    window.setTimeout(() => {
        $(form).find('select.select2-hidden-accessible').trigger('change.select2');
    }, 0);
});


function initializeDatePickers(root = document) {
    $(root).find('input[type="date"]:not(.no-datepicker)').each(function () {
        if (this._flatpickr) return;
        flatpickr(this, { dateFormat: 'Y-m-d', allowInput: true, disableMobile: false });
    });
}

window.initializeDatePickers = initializeDatePickers;
$(function () {
    initializeDatePickers(document);

    // Breadcrumbs are disabled; keep legacy action headers only when they contain controls.
    $('.fb-header').each(function () {
        const $header = $(this);
        if ($header.find('.btn, button, [role="button"]').length === 0 && $header.find('nav[aria-label="breadcrumb"]').length) {
            $header.hide();
        } else {
            $header.addClass('fb-header-actions-only');
        }
    });

    // Reuse the real action controls in each list card header. This supports
    // plain table wrappers and list cards nested inside a Bootstrap row.
    document.querySelectorAll('.fb-content').forEach(content => {
        const header = Array.from(content.children).find(el => el.classList.contains('fb-header'));
        if (!header || !header.querySelector('button, a.btn, [role="button"]')) return;
        const workspace = Array.from(content.children).find(el => el.classList.contains('fb-main'));
        if (!workspace) return;
        const isList = el => el.matches('.fb-table-wrapper, .wes-reference-table') ||
            (el.matches('.card') && !el.matches('#createCard, .wes-accounts-form-card, .fb-inline-form-panel') &&
                el.querySelector('table') && !el.querySelector('form'));
        let list = Array.from(workspace.children).find(isList);
        if (!list) {
            // Some reports put their table in a single responsive grid column.
            const candidates = Array.from(workspace.children).filter(el => el.matches('.row'))
                .flatMap(row => Array.from(row.children).flatMap(col => [col, ...Array.from(col.children)]))
                .filter(isList);
            if (candidates.length === 1) list = candidates[0];
        }
        if (!list || list.querySelector(':scope > .wes-integrated-card-header')) return;
        const actionsToMove = Array.from(header.querySelectorAll('button, a.btn, [role="button"]'))
            .filter(button => !button.closest('.breadcrumb, form'));
        if (!actionsToMove.length) return;
        const heading = document.createElement('div');
        heading.className = 'card-header wes-integrated-card-header';
        const oldTitle = Array.from(list.children).find(el => el.matches('.card-title'));
        const label = oldTitle?.textContent?.trim() ||
            header.querySelector('h1,h2,h3,h4,h5,h6,.breadcrumb-item.active')?.textContent?.trim();
        const title = document.createElement('h4');
        title.className = 'wes-integrated-card-title';
        title.textContent = label || document.title.split(' - ')[0] || 'Records';
        heading.appendChild(title);
        const actions = document.createElement('div');
        actions.className = 'wes-integrated-card-actions';
        actionsToMove.forEach(button => actions.appendChild(button));
        heading.appendChild(actions);
        list.prepend(heading);
        header.remove();
        list.classList.add('wes-integrated-card');
        if (oldTitle) oldTitle.remove();
    });

    const currentPath = window.location.pathname.replace(/\/$/, '');
    $('.fb-sidebar button').each(function () {
        const handler = this.getAttribute('onclick') || '';
        const match = handler.match(/['"]([^'"]+)['"]/);
        if (match) {
            try {
                const targetPath = new URL(match[1], window.location.origin).pathname.replace(/\/$/, '');
                if (targetPath && currentPath.startsWith(targetPath)) $(this).addClass('active');
            } catch (_) {}
        }
    });
});
$(document).on('shown.bs.modal', '.modal', function () { initializeDatePickers(this); });



// In-page form panels used across WeSarafi modules.
// They replace slide-over forms so long forms remain stable and the list
// is hidden while the user creates/edits a record.
function resolveInlineFormTarget(target) {
    if (!target) return null;
    if (target instanceof HTMLElement) return target;
    return document.querySelector(target);
}

function inlineFormContext(panel) {
    if (!panel) return { content: null, list: null };
    let content = panel.closest('.fb-content');
    if (!content) {
        const trigger = document.querySelector(`[data-wes-form-target="#${panel.id}"]`);
        content = trigger?.closest('.fb-content') || document.querySelector('.fb-content');
    }
    const list = content?.querySelector('.fb-main') || null;
    return { content, list };
}

window.wesOpenInlineForm = function (target) {
    const panel = resolveInlineFormTarget(target);
    if (!panel) return;
    const { content, list } = inlineFormContext(panel);

    if (content && panel.parentElement !== content) {
        content.appendChild(panel);
    }

    content?.querySelectorAll('.fb-inline-form-panel').forEach(el => {
        if (el !== panel) el.classList.add('d-none');
    });

    list?.classList.add('fb-inline-list-hidden');
    panel.classList.remove('d-none');
    panel.dispatchEvent(new CustomEvent('wes:inline-form:show', { bubbles: true }));
    window.initializeSelect2?.(panel);
    window.initializeDatePickers?.(panel);

    const scrollTarget = content || panel;
    if (scrollTarget && typeof scrollTarget.scrollTo === 'function') {
        scrollTarget.scrollTo({ top: 0, behavior: 'auto' });
    }
    panel.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus({ preventScroll: true });
};

window.wesCloseInlineForm = function (target) {
    const panel = resolveInlineFormTarget(target);
    if (!panel) return;
    const { content, list } = inlineFormContext(panel);

    panel.dispatchEvent(new CustomEvent('wes:inline-form:hide', { bubbles: true }));
    panel.classList.add('d-none');
    if (!content?.querySelector('.fb-inline-form-panel:not(.d-none)')) {
        list?.classList.remove('fb-inline-list-hidden');
    }

    if (window.jQuery && window.jQuery.fn?.dataTable) {
        window.setTimeout(() => {
            window.jQuery.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }, 0);
    }
};

$(document).on('click', '[data-wes-form-target]', function (event) {
    event.preventDefault();
    const target = this.getAttribute('data-wes-form-target');
    window.wesOpenInlineForm(target);
});

$(document).on('click', '[data-wes-form-close]', function (event) {
    event.preventDefault();
    const panel = this.closest('.fb-inline-form-panel');
    window.wesCloseInlineForm(panel);
});

// Legacy create/edit cards use jQuery show/hide instead of inline panels.
// Observe their actual state so editing and successful saves follow the same rule.
function syncLegacyFormTables() {
    document.querySelectorAll('.fb-content').forEach(content => {
        const form = content.querySelector('#createCard');
        const table = content.querySelector('#tableCard');
        if (!form || !table) return;
        const sync = () => {
            const open = !form.hidden && getComputedStyle(form).display !== 'none';
            table.classList.toggle('fb-form-table-hidden', open);
        };
        new MutationObserver(sync).observe(form, { attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
        sync();
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncLegacyFormTables, { once: true });
} else {
    syncLegacyFormTables();
}

// Apply the same heading/filter surfaces to every existing list without
// moving controls or replacing their event handlers and server-side tables.
function styleTablePages() {
    document.querySelectorAll('.content-wrapper .table').forEach(table => {
        if (table.closest('.modal, form, #createCard, .fb-inline-form-panel')) return;
        const card = table.closest('.fb-table-wrapper, .card, .wes-reference-table');
        if (!card) return;
        const title = card.querySelector(':scope > .card-title, :scope > .card-header, :scope > .d-flex:has(.card-title)');
        title?.classList.add('wes-table-heading');
        card.querySelectorAll('.row:has([id^="filter"])').forEach(row => row.classList.add('wes-table-filters'));
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', styleTablePages, { once: true });
} else {
    styleTablePages();
}

// Inline Blade page scripts are intentionally executed only after this module
// has exposed jQuery/Bootstrap/DataTables/Select2/Flatpickr/SweetAlert globals.
window.dispatchEvent(new CustomEvent('wes:app-ready'));
