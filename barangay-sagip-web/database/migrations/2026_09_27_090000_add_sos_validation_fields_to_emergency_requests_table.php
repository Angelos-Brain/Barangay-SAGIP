<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 2: SOS reason, corroborating data, and post-incident outcome.
     *
     * Every column is nullable so ordinary form reports and SOS incidents filed
     * before this change stay valid untouched.
     */
    public function up(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->string('sos_reason')->nullable()->after('sos_channel');
            $table->string('sos_reason_other', 100)->nullable()->after('sos_reason');
            $table->string('device_id', 64)->nullable()->after('sos_reason_other');
            // Snapshot of the reporter's account status when the SOS was raised.
            $table->string('reporter_verification_status')->nullable()->after('device_id');
            // e.g. ["poor_accuracy", "outside_bounds"] — informational, never blocking.
            $table->json('location_flags')->nullable()->after('location_accuracy_meters');
            $table->string('attachment_path')->nullable()->after('location_flags');

            $table->string('outcome')->nullable()->after('status');
            $table->foreignId('outcome_set_by')->nullable()->after('outcome')->constrained('users')->nullOnDelete();
            $table->timestamp('outcome_set_at')->nullable()->after('outcome_set_by');

            $table->index(['resident_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->dropIndex(['resident_id', 'outcome']);
            $table->dropConstrainedForeignId('outcome_set_by');
            $table->dropColumn([
                'sos_reason',
                'sos_reason_other',
                'device_id',
                'reporter_verification_status',
                'location_flags',
                'attachment_path',
                'outcome',
                'outcome_set_at',
            ]);
        });
    }
};
