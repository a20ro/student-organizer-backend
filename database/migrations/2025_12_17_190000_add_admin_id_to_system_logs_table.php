<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            // SQLite doesn't support adding foreign key columns easily, so we'll add it as nullable
            DB::statement('ALTER TABLE system_logs ADD COLUMN admin_id INTEGER NULL');
        } else {
            Schema::table('system_logs', function (Blueprint $table) {
                $table->foreignId('admin_id')->nullable()->after('user_id')->constrained('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            // SQLite doesn't support dropping columns easily, so we'll need to recreate the table
            DB::statement('PRAGMA foreign_keys=OFF;');
            
            DB::statement("
                CREATE TABLE system_logs_new (
                    id integer primary key autoincrement not null,
                    type varchar not null,
                    level varchar not null default 'info',
                    message text not null,
                    context text,
                    user_id varchar,
                    ip_address varchar,
                    user_agent varchar,
                    created_at datetime,
                    updated_at datetime
                );
            ");
            
            DB::statement("
                INSERT INTO system_logs_new (
                    id, type, level, message, context, user_id, ip_address, user_agent, created_at, updated_at
                )
                SELECT
                    id, type, level, message, context, user_id, ip_address, user_agent, created_at, updated_at
                FROM system_logs;
            ");
            
            DB::statement('DROP TABLE system_logs;');
            DB::statement('ALTER TABLE system_logs_new RENAME TO system_logs;');
            DB::statement('CREATE INDEX system_logs_created_at_index on system_logs (created_at);');
            DB::statement('CREATE INDEX system_logs_type_index on system_logs (type);');
            
            DB::statement('PRAGMA foreign_keys=ON;');
        } else {
            Schema::table('system_logs', function (Blueprint $table) {
                $table->dropForeign(['admin_id']);
                $table->dropColumn('admin_id');
            });
        }
    }
};
