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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('chart_of_account_id');
            $table->unsignedBigInteger('account_id')->nullable(); // Paid from
            $table->unsignedBigInteger('currency_id');
            $table->decimal('amount', 20, 2);
            $table->string('payment_type')->default('cash'); // cash or credit
            $table->string('payment_status')->default('paid'); // paid, partial, unpaid
            $table->date('date');
            $table->date('due_date')->nullable(); // for unpaid credit
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('created_by')->references('id')->on('users');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
