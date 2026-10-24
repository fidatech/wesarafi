<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fx_positions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('currency_id')->constrained()->cascadeOnDelete(); // USD, EUR (not AFN)
            $t->decimal('qty', 24, 8)->default(0);          // e.g., 10,000.00000000 USD
            $t->decimal('avg_cost_rate_afn', 24, 8)->default(0); // AFN per 1 unit (moving average)
            $t->timestamps();
            $t->unique(['branch_id','currency_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fx_positions');
    }
};
