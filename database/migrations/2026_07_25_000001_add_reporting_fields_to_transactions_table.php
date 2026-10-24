<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('currency_id')->index();
            }

            if (! Schema::hasColumn('transactions', 'description')) {
                $table->text('description')->nullable()->after('direction');
            }

            if (! Schema::hasColumn('transactions', 'exchange_rate_afn')) {
                $table->decimal('exchange_rate_afn', 24, 8)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('transactions', 'amount_afn')) {
                $table->decimal('amount_afn', 24, 2)->nullable()->after('exchange_rate_afn');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            foreach (['amount_afn', 'exchange_rate_afn', 'description', 'account_id'] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
