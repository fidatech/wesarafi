<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('remittances', function (Blueprint $table) {
            $table->string('sender_father_name')->nullable()->after('sender_name');
            $table->string('sender_tazkira_number')->nullable()->after('sender_phone');
            $table->string('sender_tazkira_file')->nullable()->after('sender_tazkira_number');
            $table->string('receiver_father_name')->nullable()->after('receiver_name');
            $table->string('receiver_tazkira_number')->nullable()->after('receiver_phone');
            $table->string('receiver_tazkira_file')->nullable()->after('receiver_tazkira_number');
            $table->string('receiver_live_photo')->nullable()->after('receiver_tazkira_file');
            $table->longText('receiver_fingerprint')->nullable()->after('receiver_live_photo');
            $table->decimal('sender_branch_commission', 18, 4)->nullable()->after('commission');
            $table->decimal('receiver_branch_commission', 18, 4)->nullable()->after('sender_branch_commission');
            $table->foreignId('completed_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('remittances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn([
                'sender_father_name', 'sender_tazkira_number', 'sender_tazkira_file',
                'receiver_father_name', 'receiver_tazkira_number', 'receiver_tazkira_file',
                'receiver_live_photo', 'receiver_fingerprint', 'sender_branch_commission',
                'receiver_branch_commission', 'completed_at',
            ]);
        });
    }
};
