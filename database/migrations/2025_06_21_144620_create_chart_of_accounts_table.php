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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 1000 for Cash on Hand
            $table->string('category');       // Assets, Liabilities, Equity, etc.
            $table->string('subcategory')->nullable(); // Current, Fixed, etc.
            $table->string('account_name');   // Cash in Bank, Salaries, etc.
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
