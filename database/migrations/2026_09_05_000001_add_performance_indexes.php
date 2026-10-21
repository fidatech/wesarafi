<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(
                ['branch_id', 'chart_of_account_id', 'currency_id', 'is_approved', 'date'],
                'tx_balance_lookup_idx'
            );
            $table->index(['approval_status', 'branch_id', 'date'], 'tx_approval_lookup_idx');
            $table->index(['is_suspicious', 'date'], 'tx_suspicious_date_idx');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->index(['branch_id', 'type', 'deleted_at'], 'accounts_branch_type_active_idx');
            $table->index(
                ['account_sub_category_id', 'branch_id', 'deleted_at'],
                'accounts_category_branch_idx'
            );
            $table->index(['branch_id', 'is_active', 'deleted_at'], 'accounts_branch_status_idx');
            $table->index('phone', 'accounts_phone_idx');
            $table->index('id_card_number', 'accounts_id_card_idx');
        });

        Schema::table('customer_exchanges', function (Blueprint $table) {
            $table->index(['branch_id', 'date', 'status'], 'exchange_branch_date_status_idx');
            $table->index(['customer_id', 'date', 'status'], 'exchange_customer_date_status_idx');
        });

        Schema::table('remittances', function (Blueprint $table) {
            $table->index(['from_branch_id', 'date', 'status'], 'remit_from_branch_date_idx');
            $table->index(['to_branch_id', 'date', 'status'], 'remit_to_branch_date_idx');
            $table->index(['sender_account_id', 'date'], 'remit_sender_date_idx');
            $table->index(['receiver_account_id', 'date'], 'remit_receiver_date_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index(['branch_id', 'is_active', 'designation_id'], 'employees_branch_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('tx_balance_lookup_idx');
            $table->dropIndex('tx_approval_lookup_idx');
            $table->dropIndex('tx_suspicious_date_idx');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('accounts_branch_type_active_idx');
            $table->dropIndex('accounts_category_branch_idx');
            $table->dropIndex('accounts_branch_status_idx');
            $table->dropIndex('accounts_phone_idx');
            $table->dropIndex('accounts_id_card_idx');
        });

        Schema::table('customer_exchanges', function (Blueprint $table) {
            $table->dropIndex('exchange_branch_date_status_idx');
            $table->dropIndex('exchange_customer_date_status_idx');
        });

        Schema::table('remittances', function (Blueprint $table) {
            $table->dropIndex('remit_from_branch_date_idx');
            $table->dropIndex('remit_to_branch_date_idx');
            $table->dropIndex('remit_sender_date_idx');
            $table->dropIndex('remit_receiver_date_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_branch_active_idx');
        });
    }
};
