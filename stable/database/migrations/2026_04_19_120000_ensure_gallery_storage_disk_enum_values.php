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

        DB::unprepared(<<<'SQL'
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'storage_disk') THEN
                RETURN;
            END IF;

            IF NOT EXISTS (
                SELECT 1
                FROM pg_enum e
                INNER JOIN pg_type t ON e.enumtypid = t.oid
                WHERE t.typname = 'storage_disk' AND e.enumlabel = 'private'
            ) THEN
                ALTER TYPE storage_disk ADD VALUE 'private';
            END IF;

            IF NOT EXISTS (
                SELECT 1
                FROM pg_enum e
                INNER JOIN pg_type t ON e.enumtypid = t.oid
                WHERE t.typname = 'storage_disk' AND e.enumlabel = 'public'
            ) THEN
                ALTER TYPE storage_disk ADD VALUE 'public';
            END IF;
        END$$;
        SQL);
    }

    public function down(): void
    {
        // PostgreSQL の ENUM から値を安全に削除できないため何もしない。
    }
};