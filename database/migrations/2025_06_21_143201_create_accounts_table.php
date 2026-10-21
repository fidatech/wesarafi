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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('account_code')->unique(); // e.g., ACC-0001
            $table->string('full_name');
            $table->string('father_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Type and Classification
            $table->enum('type', ['safe', 'customer', 'bank', 'branch'])->index(); // Main account type
            $table->foreignId('account_sub_category_id')->nullable()->constrained()->nullOnDelete(); // e.g., capital, legal customer, etc.

            // Branch Assignment
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            // KYC Info
            $table->date('date_of_birth')->nullable();
            $table->date('kyc_date')->nullable();
            $table->string('occupation')->nullable();
            $table->string('monthly_income')->nullable();

            // ID Card Info
            $table->string('id_card_type')->nullable();         // e.g., Tazkira, Passport
            $table->string('id_card_number')->nullable();
            $table->string('id_card_file')->nullable();          // PDF/Image

            // Business Info (For corporate/legal customers)
            $table->string('business_name')->nullable();
            $table->string('tin')->nullable();
            $table->string('license_type')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_issue_date')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('account_number')->nullable();        // For internal/legal use
            $table->string('shop_house_no')->nullable();

            //Count Level
            $table->boolean('is_pep')->default(false);
            $table->boolean('is_sanction_list_applied')->default(false);
            $table->foreignId('income_source_id')->nullable()->constrained('income_sources')->nullOnDelete();
            $table->foreignId('nationality_id')->nullable()->constrained('nationalities')->nullOnDelete();
            $table->string('transaction_volume')->nullable();
            $table->string('purpose')->nullable();               // For internal notes
            $table->string('geographical_area')->nullable();
            $table->string('distribution_channels')->nullable();

            // Status & Risk
            $table->enum('risk_level', ['low', 'normal', 'high'])->default('low');
            $table->boolean('is_active')->default(true);

            // Address & Media
            $table->text('address')->nullable();
            $table->string('profile_picture')->default('no_image.png');

            // App User Link (Optional future mobile use)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
