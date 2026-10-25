<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstitutionalTableMarkupTest extends TestCase
{
    public static function interactivePages(): array
    {
        return [
            'regular customers' => ['accounts/regular-customers/index.blade.php'],
            'users' => ['configuration/users/index.blade.php'],
            'remittances' => ['remittances/index.blade.php'],
            'journal' => ['journal/transactions/index.blade.php'],
            'payroll' => ['hr/payroll/index.blade.php'],
        ];
    }

    #[DataProvider('interactivePages')]
    public function test_interactive_tables_have_responsive_institutional_wrappers(string $view): void
    {
        $markup = file_get_contents(resource_path('views/'.$view));

        $this->assertStringContainsString('table-responsive wes-institutional-table', $markup);
        $this->assertStringContainsString('<table', $markup);
    }

    public function test_users_datatable_columns_match_headers_without_duplicates(): void
    {
        $markup = file_get_contents(resource_path('views/configuration/users/index.blade.php'));
        preg_match('/<table[^>]*id="users-table"[^>]*>.*?<thead[^>]*>(.*?)<\/thead>/s', $markup, $header);
        preg_match('/columns:\s*\[(.*?)\]\s*\}\);/s', $markup, $columns);

        $this->assertNotEmpty($header, 'Users table header must be present.');
        $this->assertNotEmpty($columns, 'Users DataTables column configuration must be present.');
        $this->assertSame(substr_count($header[1], '<th'), substr_count($columns[1], 'data:'),
            'Users table headers and DataTables columns must have the same count.');
        $this->assertSame(1, substr_count($columns[1], "data: 'username'"),
            'Username must not be rendered twice.');
    }

    public function test_users_search_is_debounced_before_server_reload(): void
    {
        $markup = file_get_contents(resource_path('views/configuration/users/index.blade.php'));

        $this->assertStringContainsString("$('#searchBox').on('input'", $markup);
        $this->assertStringContainsString('clearTimeout(searchTimer)', $markup);
        $this->assertStringContainsString('setTimeout(() => userTable.ajax.reload(), 300)', $markup);
    }

    public function test_branches_search_is_debounced_without_delaying_filters(): void
    {
        $markup = file_get_contents(resource_path('views/configuration/branches/index.blade.php'));

        $this->assertStringContainsString("$('#searchBox').on('input'", $markup);
        $this->assertStringContainsString('clearTimeout(searchTimer)', $markup);
        $this->assertStringContainsString('setTimeout(() => table.ajax.reload(), 300)', $markup);
        $this->assertStringContainsString("$('#filterType, #filterProvince').on('change'", $markup);
    }

    public function test_roles_permission_expander_is_rendered_for_hidden_groups(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/RoleController.php'));
        $view = file_get_contents(resource_path('views/configuration/roles/index.blade.php'));

        $this->assertStringContainsString('if (count($grouped) > 3)', $controller);
        $this->assertStringContainsString('aria-expanded="false"', $controller);
        $this->assertStringContainsString('function togglePermissions(roleId, button)', $view);
        $this->assertStringContainsString("button.setAttribute('aria-expanded', String(expanded))", $view);
        $this->assertStringNotContainsString('const btn = event.target', $view);
    }

    public function test_audit_log_filters_have_accessible_labels(): void
    {
        $markup = file_get_contents(resource_path('views/configuration/auditlogs/index.blade.php'));
        foreach (['filter_user', 'filter_date', 'filter_action'] as $id) {
            $this->assertStringContainsString('for="'.$id.'"', $markup);
            $this->assertStringContainsString('id="'.$id.'"', $markup);
        }
        $this->assertStringContainsString('allowInput: true', $markup);
    }

    public function test_page_scripts_wait_for_vite_globals(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/layoutMaster.blade.php'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("@vite(['resources/css/app.css', 'resources/js/app.js'])", $layout);
        $this->assertStringContainsString('id="wesDeferredScripts"', $layout);
        $this->assertStringContainsString("window.addEventListener('wes:app-ready'", $layout);
        $this->assertStringContainsString('window.jQuery = $;', $app);
        $this->assertStringContainsString("window.dispatchEvent(new CustomEvent('wes:app-ready'))", $app);
    }
}
