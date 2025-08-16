\encoding UTF8
-- SET client_encoding TO 'UTF8';
-- ====================
-- @usage: psql -U <username> -d <dbname> -f create_logs_tables.sql
-- ====================
-- ログテーブル初期化
-- ====================
DROP TABLE IF EXISTS logs_p0, logs_p1, logs_p2, logs_p3, logs_p4, logs_p5, logs_p6, logs_p7, logs_p8, logs_p9, logs CASCADE;

-- ====================
-- 代表（親）ログテーブル作成
-- ====================
CREATE TABLE logs (
    id              BIGSERIAL,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    app_id          INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
    log_date        DATE NOT NULL,
    total_pulls     INTEGER NOT NULL,
    discharge_items INTEGER NOT NULL,
    expense_amount  BIGINT NOT NULL DEFAULT 0, -- 最小単位での金額（負を許容しない）
    drop_details    JSONB,
    tags            JSONB,
    free_text       TEXT,
    images          JSONB,
    tasks           JSONB,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, id),
    UNIQUE (user_id, app_id, log_date),
    CONSTRAINT expense_non_negative CHECK (expense_amount >= 0)
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

-- ====================
-- 補助インデックスの作成
-- ====================
CREATE INDEX ON logs (user_id, app_id, log_date DESC);
CREATE INDEX ON logs (user_id, log_date DESC);
CREATE INDEX ON logs (user_id, expense_amount);

-- ====================
-- アプリの通貨桁数で割って小数化して返すVIEW
-- ====================
CREATE OR REPLACE VIEW logs_with_money AS
SELECT
    l.*,
    a.currency_code,
    c.minor_unit,
    (l.expense_amount::numeric / (10::numeric ^ c.minor_unit)) AS expense_decimal
FROM logs l
JOIN apps a ON a.id = l.app_id
JOIN currencies c ON c.code = a.currency_code;
