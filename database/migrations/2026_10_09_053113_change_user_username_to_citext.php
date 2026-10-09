<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove the expression index first.
        DB::statement('DROP INDEX IF EXISTS users_username_lower_unique');

        /**
         * Convert the column to case-insensitive text.
         * 
         * No whereRaw() and no manual lowercasing, example:
         * $user = User::whereRaw('LOWER(username) = LOWER(?)', [$username])->first();
         */
        DB::statement(
            'ALTER TABLE users
            ALTER COLUMN username TYPE citext
            USING username::citext'
        );

        // Add normal unique index on the citext column.
        DB::statement('CREATE UNIQUE INDEX users_username_unique ON users (username)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE users
            ALTER COLUMN username TYPE varchar(255)
            USING username::varchar'
        );

        DB::statement('CREATE UNIQUE INDEX users_username_lower_unique ON users (LOWER(username))');
    }
};
