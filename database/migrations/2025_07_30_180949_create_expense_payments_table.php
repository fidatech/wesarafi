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
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id');
            $table->unsignedBigInteger('account_id'); // Paid from this account
            $table->unsignedBigInteger('currency_id');
            $table->decimal('amount', 20, 2);
            $table->date('date');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('expense_id')->references('id')->on('expenses')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
