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
        // Check if column exists before dropping (it may not exist if never added)
        if (Schema::hasColumn('transactions', 'tags')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('tags');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only add column if it doesn't exist
        if (!Schema::hasColumn('transactions', 'tags')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->text('tags')->nullable(); // JSON array
            });
        }
    }
};
