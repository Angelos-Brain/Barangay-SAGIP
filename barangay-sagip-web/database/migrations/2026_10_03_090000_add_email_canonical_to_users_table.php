<?php

use App\Rules\GmailAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The inbox each address delivers to (Gmail dots and "+tags" removed), so
     * two spellings of one Gmail inbox cannot open two accounts. Existing
     * emails are left exactly as they are; only this derived column is filled.
     *
     * Existing residents never had to confirm their email, so they are marked
     * confirmed here rather than locked out by the new email-link requirement.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_canonical')->nullable()->after('email');
        });

        DB::table('users')->select(['id', 'email'])->orderBy('id')->each(function (object $user) {
            DB::table('users')->where('id', $user->id)->update([
                'email_canonical' => GmailAddress::canonical($user->email),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email_canonical');
        });

        DB::table('users')
            ->where('role', 'resident')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email_canonical']);
            $table->dropColumn('email_canonical');
        });
    }
};
