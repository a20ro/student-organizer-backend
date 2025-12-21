<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'start_datetime')) {
                $table->dropColumn(['start_datetime']);
            }
            if (Schema::hasColumn('events', 'end_datetime')) {
                $table->dropColumn(['end_datetime']);
            }
            if (Schema::hasColumn('events', 'course_id')) {
                $table->dropConstrainedForeignId('course_id');
            }
            if (Schema::hasColumn('events', 'google_event_id')) {
                $table->dropColumn(['google_event_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'start_datetime')) {
                $table->dateTime('start_datetime')->nullable();
            }
            if (!Schema::hasColumn('events', 'end_datetime')) {
                $table->dateTime('end_datetime')->nullable();
            }
            if (!Schema::hasColumn('events', 'course_id')) {
                $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('events', 'google_event_id')) {
                $table->string('google_event_id')->nullable();
            }
        });
    }
};

