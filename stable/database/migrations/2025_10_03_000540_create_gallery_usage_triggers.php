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
        CREATE OR REPLACE FUNCTION gallery_total_bytes(rec gallery_assets)
        RETURNS BIGINT AS $$
        BEGIN
            RETURN COALESCE(rec.bytes, 0) + COALESCE(rec.bytes_thumb_small, 0) + COALESCE(rec.bytes_thumb_large, 0);
        END;
        $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION gallery_usage_add(u BIGINT, add_bytes BIGINT, add_files BIGINT)
        RETURNS VOID AS $$
        BEGIN
            INSERT INTO gallery_usage_stats (user_id, bytes_used, files_count, created_at, updated_at)
            VALUES (u, GREATEST(add_bytes, 0), GREATEST(add_files, 0), NOW(), NOW())
            ON CONFLICT (user_id) DO UPDATE SET
                bytes_used = GREATEST(gallery_usage_stats.bytes_used + add_bytes, 0),
                files_count = GREATEST(gallery_usage_stats.files_count + add_files, 0),
                updated_at = NOW();
        END;
        $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION trg_gallery_assets_insert()
        RETURNS TRIGGER AS $$
        DECLARE delta BIGINT;
        BEGIN
            IF NEW.deleted_at IS NULL THEN
                delta := gallery_total_bytes(NEW);
                PERFORM gallery_usage_add(NEW.user_id, delta, 1);
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION trg_gallery_assets_update()
        RETURNS TRIGGER AS $$
        DECLARE old_total BIGINT;
        DECLARE new_total BIGINT;
        DECLARE delta BIGINT;
        BEGIN
            old_total := gallery_total_bytes(OLD);
            new_total := gallery_total_bytes(NEW);

            IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NULL THEN
                delta := new_total - old_total;
                IF delta != 0 THEN
                    PERFORM gallery_usage_add(NEW.user_id, delta, 0);
                END IF;
            ELSIF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL THEN
                PERFORM gallery_usage_add(NEW.user_id, -old_total, -1);
            ELSIF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                PERFORM gallery_usage_add(NEW.user_id, new_total, 1);
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION trg_gallery_assets_delete()
        RETURNS TRIGGER AS $$
        DECLARE old_total BIGINT;
        BEGIN
            IF OLD.deleted_at IS NULL THEN
                old_total := gallery_total_bytes(OLD);
                PERFORM gallery_usage_add(OLD.user_id, -old_total, -1);
            END IF;
            RETURN OLD;
        END;
        $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared("CREATE TRIGGER t_gallery_assets_insert AFTER INSERT ON gallery_assets FOR EACH ROW EXECUTE FUNCTION trg_gallery_assets_insert();");
        DB::unprepared("CREATE TRIGGER t_gallery_assets_update AFTER UPDATE OF bytes, bytes_thumb_small, bytes_thumb_large, deleted_at ON gallery_assets FOR EACH ROW EXECUTE FUNCTION trg_gallery_assets_update();");
        DB::unprepared("CREATE TRIGGER t_gallery_assets_delete AFTER DELETE ON gallery_assets FOR EACH ROW EXECUTE FUNCTION trg_gallery_assets_delete();");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared("DROP TRIGGER IF EXISTS t_gallery_assets_insert ON gallery_assets;");
        DB::unprepared("DROP TRIGGER IF EXISTS t_gallery_assets_update ON gallery_assets;");
        DB::unprepared("DROP TRIGGER IF EXISTS t_gallery_assets_delete ON gallery_assets;");
        DB::unprepared("DROP FUNCTION IF EXISTS trg_gallery_assets_insert();");
        DB::unprepared("DROP FUNCTION IF EXISTS trg_gallery_assets_update();");
        DB::unprepared("DROP FUNCTION IF EXISTS trg_gallery_assets_delete();");
        DB::unprepared("DROP FUNCTION IF EXISTS gallery_usage_add(BIGINT, BIGINT, BIGINT);");
        DB::unprepared("DROP FUNCTION IF EXISTS gallery_total_bytes(gallery_assets);");
    }
};
