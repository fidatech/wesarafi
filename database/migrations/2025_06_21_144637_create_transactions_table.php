<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('linked_id')->nullable()->index();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->date('date');

            // Essential COA linkage (central to all accounting logic)
            $table->foreignId('chart_of_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);

            // Replace ambiguous 'type' with 'direction'
            $table->enum('direction', ['credit', 'debit']); // More semantic and aligns with double-entry standards

            // Optional: Can be used for tagging like "Capital Injection", "Asset Purchase"
            $table->foreignId('transaction_type_id')->nullable()->constrained()->nullOnDelete();

            // Reference to originating module (like capital, expense, etc.)
            $table->string('module_name')->nullable();
            $table->unsignedBigInteger('module_id')->nullable();

            // Reference to accountable entity (customer, supplier, bank, etc.)
            $table->string('accountable_type')->nullable();
            $table->unsignedBigInteger('accountable_id')->nullable();

            // Optional flags
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_safe')->default(false); // Optional: marks cash vs. non-cash if needed

            // Created & deleted by
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
}
