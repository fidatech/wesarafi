<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fx_rate_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('rate_date');
            $table->decimal('rate_afn_per_unit', 24, 8);
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['branch_id', 'currency_id', 'rate_date', 'recorded_at'], 'fx_update_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rate_updates');
    }
};
