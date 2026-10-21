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
        Schema::create('auction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();

            // What you GIVE and what you GET on this line
            $table->foreignId('give_currency_id')->constrained('currencies');
            $table->decimal('give_amount', 18, 4);

            $table->foreignId('get_currency_id')->constrained('currencies');
            $table->decimal('rate_get_per_give', 18, 8)->nullable(); // optional helper; can be computed
            $table->decimal('get_amount', 18, 4);

            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auction_lines');
    }
};
