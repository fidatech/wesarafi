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
        Schema::create('account_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_fa')->nullable();
            $table->string('name_ps')->nullable();
            $table->enum('type', ['safe', 'customer', 'bank', 'branch'])->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_sub_categories');
    }
};
