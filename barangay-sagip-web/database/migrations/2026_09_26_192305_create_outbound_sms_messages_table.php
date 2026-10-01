<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 2: SOS SMS fallback.
     *
     * Every outbound message is persisted before the gateway is called, so a
     * fallback that was attempted is provable even when the gateway itself is
     * unreachable.
     */
    public function up(): void
    {
        Schema::create('outbound_sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('emergency_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose')->default('sos');
            $table->string('driver');
            $table->string('recipient');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->string('failure_reason', 500)->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_sms_messages');
    }
};
