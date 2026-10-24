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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_type'); // e.g., 'tangible', 'intangible'
            $table->string('name');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('account_id')->nullable(); // optional link to accountable entity
            $table->unsignedBigInteger('currency_id');
            $table->decimal('amount', 20, 2);
            $table->date('date');
            $table->unsignedBigInteger('chart_of_account_id');
            $table->text('description')->nullable();
            $table->unsignedInteger('useful_life_years')->nullable(); // optional, based on checkbox
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
