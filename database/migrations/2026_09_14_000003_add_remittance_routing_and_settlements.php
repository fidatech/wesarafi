<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $t) {
            $t->boolean('is_main')->default(false)->index();
            $t->boolean('allows_direct_remittance')->default(true);
        });
        $main = DB::table('branches')->where('name', 'Main Branch')->orderBy('id')->value('id');
        if ($main) {
            DB::table('branches')->where('id', $main)->update(['is_main' => true]);
        }
        Schema::table('remittances', function (Blueprint $t) {
            $t->foreignId('bridge_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $t->string('routing_mode')->default('direct');
            $t->string('collection_method')->default('cash');
            $t->decimal('bridge_commission', 18, 4)->default(0);
            $t->unsignedTinyInteger('workflow_version')->default(1);
        });
        Schema::create('remittance_legs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('remittance_id')->constrained()->restrictOnDelete();
            $t->foreignId('debtor_branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignId('creditor_branch_id')->constrained('branches')->restrictOnDelete();
            $t->decimal('amount', 18, 4);
            $t->timestamps();
            $t->unique(['remittance_id', 'debtor_branch_id', 'creditor_branch_id'], 'remittance_leg_route_unique');
        });
        Schema::create('remittance_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('remittance_leg_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 18, 4);
            $t->decimal('exchange_rate_afn', 18, 8);
            $t->date('date');
            $t->string('reference')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_settlements');
        Schema::dropIfExists('remittance_legs');
        Schema::table('remittances', function (Blueprint $t) {
            $t->dropConstrainedForeignId('bridge_branch_id');
            $t->dropColumn(['routing_mode', 'collection_method', 'bridge_commission', 'workflow_version']);
        });
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn(['is_main', 'allows_direct_remittance']));
    }
};
