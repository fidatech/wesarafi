<?php

namespace Tests\Feature;

use App\Support\JournalEntryFilter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JournalEntryFilterTest extends TestCase
{
    public function test_entry_scopes_preserve_primary_rows_and_separate_cash_and_branches(): void
    {
        config(['database.connections.journal_filter_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $schema = Schema::connection('journal_filter_test');
        $schema->create('accounts', function (Blueprint $table) {
            $table->integer('id');
            $table->string('type');
        });
        $schema->create('chart_of_accounts', function (Blueprint $table) {
            $table->integer('id');
            $table->string('code');
        });
        $schema->create('transactions', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('account_id')->nullable();
            $table->integer('chart_of_account_id')->nullable();
            $table->boolean('is_safe')->default(false);
            $table->integer('branch_id')->default(1);
            $table->integer('amount')->default(100);
        });
        $db = DB::connection('journal_filter_test');
        $db->table('accounts')->insert([['id' => 1, 'type' => 'customer'], ['id' => 2, 'type' => 'branch']]);
        $db->table('chart_of_accounts')->insert([
            ['id' => 1, 'code' => '1000'], ['id' => 2, 'code' => '1006'], ['id' => 3, 'code' => '2002'],
        ]);
        foreach ([
            [1, 1, null, false], // Customer entry.
            [2, null, 1, false], // Cash identified by seeded COA.
            [3, null, null, true], // Cash identified by the safe flag.
            [4, 2, null, false], // Branch account.
            [5, null, 2, false], // Due from branches.
            [6, null, 3, false], // Due to branches.
            [7, null, null, false], // Other primary entries must survive nullable IDs.
        ] as [$id, $account, $coa, $safe]) {
            $db->table('transactions')->insert([
                'id' => $id, 'account_id' => $account, 'chart_of_account_id' => $coa, 'is_safe' => $safe,
            ]);
        }
        $db->table('transactions')->insert(['id' => 8, 'branch_id' => 2, 'is_safe' => true]);

        foreach (['primary' => [1, 7], 'cash' => [2, 3], 'branch' => [4, 5, 6], 'all' => range(1, 7), 'invalid' => [1, 7]] as $scope => $ids) {
            $query = $db->table('transactions')->where('branch_id', 1);
            JournalEntryFilter::apply($query, $scope);
            $this->assertSame($ids, (clone $query)->orderBy('id')->pluck('id')->all());
            $this->assertEquals(count($ids) * 100, (clone $query)->sum('amount'));
        }
        $this->assertSame(8, $db->table('transactions')->count());
        DB::purge('journal_filter_test');
    }
}
