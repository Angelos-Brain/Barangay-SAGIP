<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a responder proved they hold their registered mobile number during
     * First Login. Together with `account_setup_completed_at` (password chosen)
     * and `email_verified_at` (confirmation link clicked) it gives the
     * unclaimed → phone_verified → active onboarding status.
     *
     * Responders who already finished the earlier setup form are treated as
     * fully onboarded, so nobody currently on duty is locked out.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone_number');
        });

        DB::table('users')
            ->whereNotNull('account_setup_completed_at')
            ->update([
                'phone_verified_at' => DB::raw('account_setup_completed_at'),
                'email_verified_at' => DB::raw('COALESCE(email_verified_at, account_setup_completed_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }
};
