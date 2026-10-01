<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets responders explain why they are marking themselves unavailable,
     * so officials can see it in Response Personnel Management.
     */
    public function up(): void
    {
        Schema::table('response_personnel', function (Blueprint $table) {
            $table->string('unavailability_reason', 500)->nullable()->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('response_personnel', function (Blueprint $table) {
            $table->dropColumn('unavailability_reason');
        });
    }
};
