<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['branch_id', 'date', 'is_approved'], 'transactions_branch_date_approved_idx');
            $table->index(['chart_of_account_id', 'currency_id', 'date'], 'transactions_coa_currency_date_idx');
            $table->index(['module_name', 'module_id'], 'transactions_module_idx');
            $table->index(['account_id', 'date'], 'transactions_account_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_branch_date_approved_idx');
            $table->dropIndex('transactions_coa_currency_date_idx');
            $table->dropIndex('transactions_module_idx');
            $table->dropIndex('transactions_account_date_idx');
        });
    }
};
