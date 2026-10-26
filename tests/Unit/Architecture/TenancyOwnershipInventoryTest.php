<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('tenancy-readiness')]
class TenancyOwnershipInventoryTest extends TestCase
{
    public function test_every_active_static_create_migration_has_an_ownership_decision(): void
    {
        $inventory = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/phase-01-table-inventory.md');
        $this->assertNotFalse($inventory);

        preg_match_all('/^\| `([^`]+)` \| (Tenant|Special review|Infrastructure — review connection|Legacy\/commented — verify) \|/m', $inventory, $matches);
        $classified = array_flip($matches[1]);
        $this->assertNotEmpty($classified);

        $migrations = glob(dirname(__DIR__, 3).'/database/migrations/*.php');
        $this->assertNotEmpty($migrations);
        $missing = [];

        foreach ($migrations as $migration) {
            $source = file_get_contents($migration);
            $this->assertNotFalse($source);
            // Strip PHP comments so commented-out legacy tables do not become requirements.
            $tokens = token_get_all($source);
            $active = '';
            foreach ($tokens as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $active .= is_array($token) ? $token[1] : $token;
            }
            preg_match_all('/Schema\s*::\s*create\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/', $active, $tables);
            foreach ($tables[1] as $table) {
                if (!isset($classified[$table])) {
                    $missing[$table] = basename($migration);
                }
            }
        }

        $this->assertSame([], $missing, 'New migration tables must be classified before tenancy migration.');
    }

    public function test_sensitive_tables_have_explicit_review_or_tenant_ownership(): void
    {
        $inventory = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/phase-01-table-inventory.md');
        foreach (['users', 'sessions', 'password_reset_tokens'] as $table) {
            $this->assertStringContainsString("| `{$table}` | Special review |", $inventory);
        }
        foreach (['transactions', 'accounts', 'branches', 'remittances', 'business_days'] as $table) {
            $this->assertStringContainsString("| `{$table}` | Tenant |", $inventory);
        }
    }
}
