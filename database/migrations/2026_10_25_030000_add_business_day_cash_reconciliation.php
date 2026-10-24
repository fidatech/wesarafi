<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('business_days', function (Blueprint $table) {
            $table->json('opening_snapshot')->nullable();
            $table->json('cash_counts')->nullable();
            $table->text('closing_notes')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('business_days', fn (Blueprint $table) => $table->dropColumn(['opening_snapshot', 'cash_counts', 'closing_notes']));
    }
};
