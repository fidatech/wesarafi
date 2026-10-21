<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the schema aligned with BranchController validation, where a province
     * is optional. The original migration applied nullable() after constrained(),
     * which can leave the column NOT NULL on some database drivers.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('province_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Do not make this column NOT NULL on rollback: existing installations
        // may legitimately contain branches without a province.
    }
};
