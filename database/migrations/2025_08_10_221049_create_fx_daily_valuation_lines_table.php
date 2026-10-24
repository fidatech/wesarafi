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

    Schema::create('fx_daily_valuation_lines', function (Blueprint $t) {
      $t->id();
      $t->foreignId('valuation_id')->constrained('fx_daily_valuations')->cascadeOnDelete();
      $t->foreignId('currency_id')->constrained()->cascadeOnDelete();
      $t->decimal('qty_close', 24, 8)->default(0);           // end-of-day position (e.g., USD units)
      $t->decimal('avg_cost_rate_afn', 24, 8)->default(0);   // your MAC if you keep it, else 0
      $t->decimal('closing_rate_afn', 24, 8)->default(0);
      $t->decimal('unrealized_pl_afn', 24, 2)->default(0);
      $t->timestamps();
      $t->unique(['valuation_id','currency_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fx_daily_valuation_lines');
    }
};
