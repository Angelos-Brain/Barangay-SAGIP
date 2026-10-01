<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 10: Personnel Specialization Tags.
     *
     * Responders may now hold several specializations. The original single
     * `specialization` string column is deliberately left in place and kept in
     * sync with the first tag, so the ML assignment payload and every existing
     * view keep working unchanged.
     */
    public function up(): void
    {
        Schema::table('response_personnel', function (Blueprint $table) {
            $table->json('specializations')->nullable()->after('specialization');
        });

        DB::table('response_personnel')
            ->select('id', 'specialization')
            ->orderBy('id')
            ->each(function (object $personnel): void {
                DB::table('response_personnel')
                    ->where('id', $personnel->id)
                    ->update(['specializations' => json_encode(array_filter([$personnel->specialization]))]);
            });
    }

    public function down(): void
    {
        Schema::table('response_personnel', function (Blueprint $table) {
            $table->dropColumn('specializations');
        });
    }
};
