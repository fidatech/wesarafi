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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();               // e.g., US Dollar
            $table->string('code', 10)->unique()->index(); // e.g., USD
            $table->string('symbol', 10)->nullable()->index(); // e.g., $
            $table->string('country')->nullable();         // e.g., United States
            $table->string('flag')->nullable();            // e.g., /assets/flags/us.svg
            $table->decimal('exchange_rate', 20, 2)->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
