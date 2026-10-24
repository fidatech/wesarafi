<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_exchanges', function (Blueprint $table) {
            $table->decimal('from_amount', 24, 8)->change();
            $table->decimal('commission_amount', 24, 8)->default(0)->change();
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount', 24, 8)->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_exchanges', function (Blueprint $table) {
            $table->decimal('from_amount', 18, 4)->change();
            $table->decimal('commission_amount', 18, 4)->default(0)->change();
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });
    }
};
