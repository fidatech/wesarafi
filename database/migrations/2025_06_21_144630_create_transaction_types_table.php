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
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');                  // e.g. Exchange Buy
            $table->string('category');              // e.g. exchange, asset, liability
            $table->enum('direction', ['credit', 'debit'])->nullable(); // Default flow
            $table->foreignId('default_coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_editable')->default(true); // Protect system records
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_types');
    }
};
