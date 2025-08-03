-- ====================
-- @usage: psql -U <username> -d <dbname> -f create_logs_tables.sql
-- ====================
-- ログテーブル初期化
-- ====================
DROP TABLE IF EXISTS logs_p0, logs_p1, logs_p2, logs_p3, logs_p4, logs_p5, logs_p6, logs_p7, logs_p8, logs_p9, logs CASCADE;

-- ====================
-- 親ログテーブル作成
-- ====================
CREATE TABLE logs (
    id              BIGSERIAL,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    app_id          INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
    log_date        DATE NOT NULL,
    total_pulls     INTEGER NOT NULL,
    discharge_items INTEGER NOT NULL,
    expense         INTEGER DEFAULT 0,
    drop_details    JSONB,
    tags            JSONB,
    free_text       TEXT,
    images          JSONB,
    tasks           JSONB,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, id),
    UNIQUE (user_id, app_id, log_date)
) PARTITION BY HASH (user_id);

-- ====================
-- ログパーティションの作成
-- - ユーザーIDの下1桁で分割 0-9:10分割（※ただしuser_idの下一桁 N は logs_pN と等価ではなく `mod(hashint4(N), 10)` に依存）
-- ====================
DO $$
BEGIN
    FOR i IN 0..9 LOOP
        EXECUTE format(
            'CREATE TABLE logs_p%1$s PARTITION OF logs FOR VALUES WITH (MODULUS 10, REMAINDER %1$s);',
            i
        );
    END LOOP;
END $$;
