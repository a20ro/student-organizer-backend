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
            DB::statement('PRAGMA foreign_keys=OFF;');

            DB::statement("
                CREATE TABLE users_new (
                    id integer primary key autoincrement not null,
                    name varchar not null,
                    email varchar not null,
                    email_verified_at datetime,
                    password varchar not null,
                    avatar varchar,
                    major varchar,
                    university varchar,
                    remember_token varchar,
                    created_at datetime,
                    updated_at datetime,
                    role varchar check (role in ('student', 'admin', 'super_admin')) not null default 'student',
                    status varchar check (status in ('active', 'suspended')) not null default 'active',
                    last_login datetime,
                    google_id varchar,
                    google_email varchar,
                    google_access_token text,
                    google_refresh_token text
                );
            ");

            DB::statement("
                INSERT INTO users_new (
                    id, name, email, email_verified_at, password, avatar, major, university,
                    remember_token, created_at, updated_at, role, status, last_login,
                    google_id, google_email, google_access_token, google_refresh_token
                )
                SELECT
                    id, name, email, email_verified_at, password, avatar, major, university,
                    remember_token, created_at, updated_at, role, status, last_login,
                    google_id, google_email, google_access_token, google_refresh_token
                FROM users;
            ");

            DB::statement('DROP TABLE users;');
            DB::statement('ALTER TABLE users_new RENAME TO users;');
            DB::statement('CREATE UNIQUE INDEX users_email_unique on users (email);');
            DB::statement('CREATE UNIQUE INDEX users_google_id_unique on users (google_id);');

            DB::statement('PRAGMA foreign_keys=ON;');
        } elseif (config('database.default') === 'pgsql' || DB::getDriverName() === 'pgsql') {
             // For PostgreSQL, we need to drop the check constraint and re-add it
             // naming convention is usually table_column_check
             DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
             DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('student', 'admin', 'super_admin'))");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['student', 'admin', 'super_admin'])->default('student')->change();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF;');

            DB::statement("
                CREATE TABLE users_old (
                    id integer primary key autoincrement not null,
                    name varchar not null,
                    email varchar not null,
                    email_verified_at datetime,
                    password varchar not null,
                    avatar varchar,
                    major varchar,
                    university varchar,
                    remember_token varchar,
                    created_at datetime,
                    updated_at datetime,
                    role varchar check (role in ('student', 'admin')) not null default 'student',
                    status varchar check (status in ('active', 'suspended')) not null default 'active',
                    last_login datetime,
                    google_id varchar,
                    google_email varchar,
                    google_access_token text,
                    google_refresh_token text
                );
            ");

            DB::statement("
                INSERT INTO users_old (
                    id, name, email, email_verified_at, password, avatar, major, university,
                    remember_token, created_at, updated_at, role, status, last_login,
                    google_id, google_email, google_access_token, google_refresh_token
                )
                SELECT
                    id, name, email, email_verified_at, password, avatar, major, university,
                    remember_token, created_at, updated_at, role, status, last_login,
                    google_id, google_email, google_access_token, google_refresh_token
                FROM users;
            ");

            DB::statement('DROP TABLE users;');
            DB::statement('ALTER TABLE users_old RENAME TO users;');
            DB::statement('CREATE UNIQUE INDEX users_email_unique on users (email);');
            DB::statement('CREATE UNIQUE INDEX users_google_id_unique on users (google_id);');

            DB::statement('PRAGMA foreign_keys=ON;');
        } elseif (config('database.default') === 'pgsql' || DB::getDriverName() === 'pgsql') {
             DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
             DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('student', 'admin'))");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['student', 'admin'])->default('student')->change();
            });
        }
    }
};
