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
            $table->date('date')->nullable()->after('description');
            $table->time('time')->nullable()->after('date');
            $table->string('location')->nullable()->after('time');
            $table->string('reminder_before')->nullable()->after('location'); // e.g., "5 min", "30 min", "1 hour"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['date', 'time', 'location', 'reminder_before']);
        });
    }
};

