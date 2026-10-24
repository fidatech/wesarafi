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
        Schema::create('fx_rates', function (Blueprint $t) {
            $t->id();
            $t->date('as_of');
            $t->enum('type', ['open', 'close']);
            $t->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete(); // null = global
            $t->decimal('rate_afn_per_unit', 24, 8);
            $t->timestamps();
            $t->unique(['as_of', 'type', 'currency_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
