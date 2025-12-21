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
        Schema::table('notes', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('content');
            $table->boolean('is_favorite')->default(false)->after('is_pinned');
            $table->string('share_token')->nullable()->unique()->after('is_favorite');
            $table->boolean('is_public')->default(false)->after('share_token');
            $table->text('tags')->nullable()->after('is_public'); // JSON array
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['is_pinned', 'is_favorite', 'share_token', 'is_public', 'tags']);
        });
    }
};
