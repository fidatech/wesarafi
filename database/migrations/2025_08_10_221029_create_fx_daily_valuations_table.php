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
        Schema::create('fx_daily_valuations', function (Blueprint $t) {
      $t->id();
      $t->date('as_of');
      $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
      $t->decimal('realized_pl_afn', 24, 2)->default(0);
      $t->decimal('unrealized_pl_afn', 24, 2)->default(0);
      $t->decimal('temp_pnl_afn', 24, 2)->default(0);
      $t->timestamps();
      $t->unique(['as_of','branch_id']);
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fx_daily_valuations');
    }
};
