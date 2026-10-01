<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 7: Tanod location lock.
     *
     * A tanod goes on duty by checking in at the barangay hall. The coordinates
     * and the measured distance are kept so a questionable check-in can be
     * reviewed after the fact, not just accepted or refused in the moment.
     */
    public function up(): void
    {
        Schema::table('response_personnel', function (Blueprint $table) {
            $table->timestamp('on_duty_at')->nullable()->after('unavailability_reason');
            $table->decimal('last_check_in_latitude', 10, 7)->nullable()->after('on_duty_at');
            $table->decimal('last_check_in_longitude', 10, 7)->nullable()->after('last_check_in_latitude');
            $table->decimal('last_check_in_distance_meters', 10, 2)->nullable()->after('last_check_in_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('response_personnel', function (Blueprint $table) {
            $table->dropColumn([
                'on_duty_at',
                'last_check_in_latitude',
                'last_check_in_longitude',
                'last_check_in_distance_meters',
            ]);
        });
    }
};
