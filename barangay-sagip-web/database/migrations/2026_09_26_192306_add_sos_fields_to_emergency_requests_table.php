<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 2: SOS button.
     *
     * An SOS is an emergency request like any other — the map, classifier,
     * assignment flow, and reports all keep working on it unchanged — so it is
     * marked with a `source` rather than split into a parallel table.
     */
    public function up(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->string('source')->default('form')->after('description');
            // form | sos | sms_fallback
            $table->string('sos_channel')->nullable()->after('source');
            // online | sms
            $table->decimal('location_accuracy_meters', 8, 2)->nullable()->after('longitude');

            $table->index(['source', 'created_at']);
        });

        DB::table('emergency_requests')->update(['source' => 'form']);
    }

    public function down(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->dropIndex(['source', 'created_at']);
            $table->dropColumn(['source', 'sos_channel', 'location_accuracy_meters']);
        });
    }
};
