<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // logs本体（パーティション用）
        DB::statement("
            CREATE TABLE logs (
                id              BIGSERIAL,
                user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                app_id          INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
                log_date        DATE NOT NULL,
                total_pulls     INTEGER NOT NULL,
                discharge_items INTEGER NOT NULL,
                expense         INTEGER DEFAULT 0,
                drop_details    drop[],
                tags            TEXT[],
                free_text       TEXT,
                images          TEXT[],
                tasks           JSONB,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (user_id, id),
                UNIQUE (user_id, app_id, log_date)
            ) PARTITION BY HASH (user_id);
        ");

        // パーティション作成（0～9）
        for ($i = 0; $i <= 9; $i++) {
            DB::statement("
                CREATE TABLE logs_p{$i} PARTITION OF logs FOR VALUES WITH (MODULUS 10, REMAINDER {$i});
            ");
        }
    }

    public function down(): void
    {
        // 先にパーティション削除
        for ($i = 0; $i <= 9; $i++) {
            DB::statement("DROP TABLE IF EXISTS logs_p{$i} CASCADE;");
        }
        DB::statement("DROP TABLE IF EXISTS logs CASCADE;");
    }
};
