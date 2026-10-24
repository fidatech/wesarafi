<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_exchanges', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_exchanges', 'rate_operator')) $table->string('rate_operator', 8)->default('multiply')->after('rate');
            if (! Schema::hasColumn('customer_exchanges', 'system_rate')) $table->decimal('system_rate', 24, 10)->nullable()->after('rate_operator');
            if (! Schema::hasColumn('customer_exchanges', 'system_to_amount')) $table->decimal('system_to_amount', 24, 8)->nullable()->after('to_amount');
            if (! Schema::hasColumn('customer_exchanges', 'rate_variance_percent')) $table->decimal('rate_variance_percent', 12, 4)->default(0)->after('system_to_amount');
            if (! Schema::hasColumn('customer_exchanges', 'profit_loss_afn')) $table->decimal('profit_loss_afn', 24, 2)->default(0)->after('rate_variance_percent');
        });
    }

    public function down(): void
    {
        Schema::table('customer_exchanges', fn (Blueprint $table) => $table->dropColumn([
            'rate_operator', 'system_rate', 'system_to_amount', 'rate_variance_percent', 'profit_loss_afn',
        ]));
    }
};
