<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('report');
            $table->string('item_key');
            $table->string('title');
            $table->string('status');
            $table->text('notes');
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->json('context');
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['branch_id', 'report', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_reviews');
    }
};
