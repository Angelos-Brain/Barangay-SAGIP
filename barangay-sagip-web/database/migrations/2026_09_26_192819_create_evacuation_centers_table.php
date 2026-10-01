<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 4: Evacuation Center Management.
     */
    public function up(): void
    {
        Schema::create('evacuation_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('current_occupancy')->default(0);
            $table->string('status')->default('open');
            // open | full | closed | standby
            $table->string('contact_person')->nullable();
            $table->string('contact_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evacuation_centers');
    }
};
