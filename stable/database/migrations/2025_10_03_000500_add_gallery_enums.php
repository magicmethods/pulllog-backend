<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'visibility') THEN
                CREATE TYPE visibility AS ENUM ('private', 'unlisted', 'public');
            END IF;
        END$$;
        SQL);

        DB::statement(<<<'SQL'
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'storage_disk') THEN
                CREATE TYPE storage_disk AS ENUM ('local', 'private', 's3', 'drive', 'dropbox');
            ELSE
                IF NOT EXISTS (
                    SELECT 1 FROM pg_enum e
                    JOIN pg_type t ON e.enumtypid = t.oid
                    WHERE t.typname = 'storage_disk' AND e.enumlabel = 'private'
                ) THEN
                    ALTER TYPE storage_disk ADD VALUE 'private';
                END IF;
            END IF;
        END$$;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("DROP TYPE IF EXISTS storage_disk CASCADE;");
        DB::statement("DROP TYPE IF EXISTS visibility CASCADE;");
    }
};
