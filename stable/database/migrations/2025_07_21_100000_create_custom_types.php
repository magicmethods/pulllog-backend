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

        // themeŒ^
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'theme') THEN
                    CREATE TYPE theme AS ENUM ('light', 'dark');
                END IF;
            END$$;
        ");

        // token_typeŒ^
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'token_type') THEN
                    CREATE TYPE token_type AS ENUM ('signup', 'reset');
                END IF;
            END$$;
        ");

        // definitionŒ^
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'definition') THEN
                    CREATE TYPE definition AS (
                        symbol VARCHAR(10),
                        label VARCHAR(64),
                        value VARCHAR(64)
                    );
                END IF;
            END$$;
        ");

        // dropŒ^
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'drop') THEN
                    CREATE TYPE drop AS (
                        rarity VARCHAR(64),
                        name VARCHAR(64),
                        marker VARCHAR(64)
                    );
                END IF;
            END$$;
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("DROP TYPE IF EXISTS drop CASCADE;");
        DB::statement("DROP TYPE IF EXISTS definition CASCADE;");
        DB::statement("DROP TYPE IF EXISTS token_type CASCADE;");
        DB::statement("DROP TYPE IF EXISTS theme CASCADE;");
    }
};