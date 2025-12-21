<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column exists before trying to drop it
        if (Schema::hasColumn('notes', 'notebook_id')) {
            if (DB::getDriverName() === 'sqlite') {
                // SQLite doesn't support dropping columns with foreign keys
                // We need to recreate the table without the notebook_id column
                DB::statement('PRAGMA foreign_keys=OFF;');
                
                // Create new table without notebook_id
                DB::statement('
                    CREATE TABLE notes_new (
                        id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                        course_id INTEGER NOT NULL,
                        title VARCHAR NOT NULL,
                        content TEXT,
                        week_number INTEGER,
                        attachments TEXT,
                        created_at DATETIME,
                        updated_at DATETIME,
                        is_pinned TINYINT(1) NOT NULL DEFAULT 0,
                        is_favorite TINYINT(1) NOT NULL DEFAULT 0,
                        share_token VARCHAR,
                        is_public TINYINT(1) NOT NULL DEFAULT 0,
                        tags TEXT,
                        FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
                    )
                ');
                
                // Copy data from old table to new table
                DB::statement('
                    INSERT INTO notes_new 
                    (id, course_id, title, content, week_number, attachments, created_at, updated_at, is_pinned, is_favorite, share_token, is_public, tags)
                    SELECT 
                    id, course_id, title, content, week_number, attachments, created_at, updated_at, is_pinned, is_favorite, share_token, is_public, tags
                    FROM notes
                ');
                
                // Drop old table
                DB::statement('DROP TABLE notes');
                
                // Rename new table
                DB::statement('ALTER TABLE notes_new RENAME TO notes');
                
                // Recreate unique index
                DB::statement('CREATE UNIQUE INDEX notes_share_token_unique ON notes (share_token)');
                
                DB::statement('PRAGMA foreign_keys=ON;');
            } else {
                Schema::table('notes', function (Blueprint $table) {
                    $table->dropForeign(['notebook_id']);
                    $table->dropColumn('notebook_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't recreate notebook_id as notebooks table doesn't exist
        // This migration is one-way
    }
};
