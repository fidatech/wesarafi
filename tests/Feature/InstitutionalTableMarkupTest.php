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
