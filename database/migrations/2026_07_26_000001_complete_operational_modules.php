<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'approval_status')) $table->string('approval_status')->default('approved')->index()->after('is_approved');
            if (!Schema::hasColumn('transactions', 'approved_by')) $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('transactions', 'approved_at')) $table->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn('transactions', 'rejection_reason')) $table->text('rejection_reason')->nullable();
            if (!Schema::hasColumn('transactions', 'is_suspicious')) $table->boolean('is_suspicious')->default(false)->index();
            if (!Schema::hasColumn('transactions', 'suspicious_reason')) $table->text('suspicious_reason')->nullable();
        });

        Schema::table('settings', function (Blueprint $table) {
            foreach ([
                'company_name','license_number','phone','email','address','logo','report_footer','default_language','timezone','date_format'
            ] as $column) if (!Schema::hasColumn('settings', $column)) $table->string($column)->nullable();
        });

        Schema::create('customer_exchanges', function (Blueprint $table) {
            $table->id(); $table->uuid('linked_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('date')->index();
            $table->foreignId('from_currency_id')->constrained('currencies');
            $table->foreignId('to_currency_id')->constrained('currencies');
            $table->decimal('from_amount',18,4); $table->decimal('rate',24,10); $table->string('rate_operator',8)->default('multiply');
            $table->decimal('system_rate',24,10)->nullable(); $table->decimal('to_amount',24,8); $table->decimal('system_to_amount',24,8)->nullable();
            $table->decimal('rate_variance_percent',12,4)->default(0); $table->decimal('profit_loss_afn',24,2)->default(0);
            $table->decimal('commission_amount',18,4)->default(0);
            $table->foreignId('commission_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('status')->default('approved')->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->softDeletes();
        });

        Schema::create('remittances', function (Blueprint $table) {
            $table->id(); $table->uuid('reference')->unique();
            $table->string('direction')->index(); // outgoing, incoming, transfer
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('sender_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('receiver_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('sender_name')->nullable(); $table->string('sender_phone')->nullable();
            $table->string('receiver_name')->nullable(); $table->string('receiver_phone')->nullable();
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('amount',18,4); $table->decimal('commission',18,4)->default(0);
            $table->decimal('exchange_rate_afn',18,8)->default(1); $table->decimal('amount_afn',18,2)->default(0);
            $table->date('date')->index(); $table->string('status')->default('pending')->index();
            $table->text('description')->nullable(); $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable(); $table->timestamps(); $table->softDeletes();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id(); $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->index(); $table->time('check_in')->nullable(); $table->time('check_out')->nullable();
            $table->string('status')->default('present')->index(); $table->decimal('overtime_hours',6,2)->default(0);
            $table->text('note')->nullable(); $table->foreignId('created_by')->constrained('users'); $table->timestamps();
            $table->unique(['employee_id','date']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id(); $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('month'); $table->unsignedSmallInteger('year');
            $table->string('status')->default('draft')->index();
            $table->decimal('gross_amount',18,2)->default(0); $table->decimal('deduction_amount',18,2)->default(0);
            $table->decimal('net_amount',18,2)->default(0); $table->date('payment_date')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users'); $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable(); $table->timestamps();
            $table->unique(['branch_id','month','year']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary',18,2); $table->decimal('allowances',18,2)->default(0);
            $table->decimal('overtime',18,2)->default(0); $table->decimal('deductions',18,2)->default(0);
            $table->decimal('tax',18,2)->default(0); $table->decimal('net_salary',18,2);
            $table->unsignedInteger('present_days')->default(0); $table->unsignedInteger('absent_days')->default(0);
            $table->timestamps(); $table->unique(['payroll_id','employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items'); Schema::dropIfExists('payrolls'); Schema::dropIfExists('attendances');
        Schema::dropIfExists('remittances'); Schema::dropIfExists('customer_exchanges');
    }
};
