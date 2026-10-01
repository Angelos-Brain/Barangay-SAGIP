<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 1: Account Verification.
     *
     * New accounts land on `pending` and cannot use the emergency features
     * until an official reviews them. Accounts that already existed before this
     * migration are backfilled to `verified` so nobody who was already using
     * the system is locked out by the upgrade.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('verification_status')->default('pending')->after('role');
            $table->timestamp('verified_at')->nullable()->after('verification_status');
            $table->foreignId('verified_by')->nullable()->after('verified_at')
                ->constrained('users')->nullOnDelete();
            $table->string('verification_note', 500)->nullable()->after('verified_by');

            $table->index(['verification_status', 'role']);
        });

        DB::table('users')->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropIndex(['verification_status', 'role']);
            $table->dropColumn(['verification_status', 'verified_at', 'verified_by', 'verification_note']);
        });
    }
};
