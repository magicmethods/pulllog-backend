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
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'theme') THEN
                CREATE TYPE theme AS ENUM ('light', 'dark');
            END IF;
        END$$;
        SQL);

        DB::statement(<<<'SQL'
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'token_type') THEN
                CREATE TYPE token_type AS ENUM ('signup', 'reset', 'remember');
            ELSE
                IF NOT EXISTS (
                    SELECT 1 FROM pg_enum e
                    JOIN pg_type t ON e.enumtypid = t.oid
                    WHERE t.typname = 'token_type' AND e.enumlabel = 'remember'
                ) THEN
                    ALTER TYPE token_type ADD VALUE 'remember';
                END IF;
            END IF;
        END$$;
        SQL);

        DB::statement(<<<'SQL'
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
        SQL);

        DB::statement(<<<'SQL'
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
        SQL);
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
