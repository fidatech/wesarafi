<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fx_rate_updates', function (Blueprint $table) {
            $table->decimal('buy_rate_afn', 24, 8)->nullable();
            $table->decimal('sell_rate_afn', 24, 8)->nullable();
            $table->string('source', 32)->default('manual');
            $table->foreignId('to_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('quoted_rate', 24, 8)->nullable();
            $table->string('rate_operator', 16)->nullable();
            $table->foreignId('customer_exchange_id')->nullable()->constrained('customer_exchanges')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fx_rate_updates', function (Blueprint $table) {
            $table->dropForeign(['to_currency_id']);
            $table->dropForeign(['customer_exchange_id']);
            $table->dropColumn(['buy_rate_afn', 'sell_rate_afn', 'source', 'to_currency_id', 'quoted_rate', 'rate_operator', 'customer_exchange_id']);
        });
    }
};
