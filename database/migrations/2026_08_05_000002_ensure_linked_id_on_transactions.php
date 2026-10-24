<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'linked_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->uuid('linked_id')->nullable()->after('id')->index();
            });
        }

        // All lines created by one module record belong to the same journal group.
        DB::table('transactions')
            ->whereNull('linked_id')
            ->whereNotNull('module_name')
            ->whereNotNull('module_id')
            ->select('module_name', 'module_id')
            ->distinct()
            ->orderBy('module_name')
            ->orderBy('module_id')
            ->get()
            ->each(function ($module) {
                DB::table('transactions')
                    ->whereNull('linked_id')
                    ->where('module_name', $module->module_name)
                    ->where('module_id', $module->module_id)
                    ->update(['linked_id' => (string) Str::uuid()]);
            });

        // Legacy standalone journal lines cannot be grouped reliably, so each gets
        // its own traceable identifier rather than remaining unlinked.
        DB::table('transactions')->whereNull('linked_id')->orderBy('id')->select('id')->get()
            ->each(fn ($row) => DB::table('transactions')->where('id', $row->id)->update([
                'linked_id' => (string) Str::uuid(),
            ]));
    }

    public function down(): void
    {
        // linked_id is accounting audit data and must not be removed on rollback.
    }
};
