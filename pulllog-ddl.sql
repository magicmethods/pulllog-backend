-- =========================================
-- PullLog Reference DDL (Spec Only / UTF-8)
-- =========================================

-- ====================
-- 型定義
-- ====================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'theme') THEN
        CREATE TYPE theme AS ENUM ('light', 'dark');
    END IF;
END$$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'token_type') THEN
        CREATE TYPE token_type AS ENUM ('signup', 'reset', 'remember');
    END IF;
END$$;

-- （参考）アプリの定義系。実運用は JSONB を採用中だが型仕様として残置
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'definition') THEN
        CREATE TYPE definition AS (
            symbol      VARCHAR(10),
            label       VARCHAR(64),
            value       VARCHAR(64)
        );
    END IF;
END$$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'drop') THEN
        CREATE TYPE drop AS (
            rarity      VARCHAR(64),
            name        VARCHAR(64),
            marker      VARCHAR(64)
        );
    END IF;
END$$;

-- ====================
-- （参照用）テーブル定義
-- ※実運用は Laravel Migration を使用
-- ====================

-- 1. プラン
CREATE TABLE plans (
    id                  SERIAL PRIMARY KEY,
    name                VARCHAR(64) NOT NULL UNIQUE,
    description         TEXT,
    max_apps            INTEGER     NOT NULL DEFAULT 5,
    max_app_name_length INTEGER     NOT NULL DEFAULT 30,
    max_app_desc_length INTEGER     NOT NULL DEFAULT 400,
    max_log_tags        INTEGER     NOT NULL DEFAULT 5,
    max_log_tag_length  INTEGER     NOT NULL DEFAULT 22,
    max_log_text_length INTEGER     NOT NULL DEFAULT 250,
    max_logs_per_app    INTEGER     NOT NULL DEFAULT -1,
    max_storage_mb      INTEGER     NOT NULL DEFAULT 100,
    price_per_month     INTEGER     NOT NULL DEFAULT 0,     -- 最小単位（例: 円）
    is_active           BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. ユーザー
CREATE TABLE users (
    id                  SERIAL PRIMARY KEY,
    email               VARCHAR(255) NOT NULL UNIQUE,
    password            VARCHAR(255) NOT NULL,
    name                VARCHAR(64)  NOT NULL,
    avatar_url          VARCHAR(255),
    roles               VARCHAR[]    NOT NULL,
    plan_id             INTEGER      NOT NULL REFERENCES plans(id) ON DELETE RESTRICT,
    plan_expiration     TIMESTAMPTZ  NOT NULL,
    language            VARCHAR(10)  NOT NULL,
    theme               theme        DEFAULT 'light',
    home_page           VARCHAR(20)  NOT NULL,
    last_login          TIMESTAMPTZ,
    last_login_ip       VARCHAR(128),
    last_login_ua       VARCHAR(255),
    is_deleted          BOOLEAN      NOT NULL DEFAULT FALSE,
    is_verified         BOOLEAN      NOT NULL DEFAULT FALSE,
    remember_token      VARCHAR(255) NOT NULL DEFAULT '',
    unread_notices      INTEGER[],
    email_verified_at   TIMESTAMPTZ,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- 3. 通貨マスタ
CREATE TABLE currencies (
    code          CHAR(3)     PRIMARY KEY,
    name          VARCHAR(64) NOT NULL,
    symbol        VARCHAR(16),
    symbol_native VARCHAR(16),
    minor_unit    SMALLINT    NOT NULL DEFAULT 0,    -- ISO 4217 minor units
    rounding      NUMERIC(8,4) NOT NULL DEFAULT 0,   -- 0.05 等
    name_plural   VARCHAR(64),
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT currencies_code_upper CHECK (code = UPPER(code))
);

-- 4. アプリ
CREATE TABLE apps (
    id                  SERIAL PRIMARY KEY,
    app_key             VARCHAR(64)  NOT NULL UNIQUE,       -- ULID 等
    name                VARCHAR(128) NOT NULL,
    url                 VARCHAR(255),
    description         TEXT,
    currency_code       CHAR(3)      NOT NULL REFERENCES currencies(code),
    date_update_time    VARCHAR(5)   NOT NULL,              -- 'HH:MM'
    sync_update_time    BOOLEAN      NOT NULL DEFAULT FALSE,
    pity_system         BOOLEAN      NOT NULL DEFAULT FALSE,
    guarantee_count     INTEGER      NOT NULL DEFAULT 0,
    rarity_defs         JSONB,
    marker_defs         JSONB,
    task_defs           JSONB,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- 5. ユーザー・アプリ Pivot
CREATE TABLE user_apps (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    app_id          INTEGER     NOT NULL REFERENCES apps(id)  ON DELETE CASCADE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, app_id)
);

-- 6. 認証トークン
CREATE TABLE auth_tokens (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token           VARCHAR(255) NOT NULL UNIQUE,
    type            token_type   NOT NULL,
    code            VARCHAR(6),
    is_used         BOOLEAN      NOT NULL DEFAULT FALSE,
    failed_attempts INTEGER      NOT NULL DEFAULT 0,
    ip              VARCHAR(128),
    ua              VARCHAR(255),
    expires_at      TIMESTAMPTZ  NOT NULL,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- 7. セッション（CSRFトークン）
CREATE TABLE user_sessions (
    csrf_token      VARCHAR(255) PRIMARY KEY,
    user_id         INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email           VARCHAR(255) NOT NULL,
    created_at      TIMESTAMPTZ  NOT NULL,
    expires_at      TIMESTAMPTZ  NOT NULL
);

-- 8. SNSアカウント連携
CREATE TABLE social_accounts (
    id               SERIAL PRIMARY KEY,
    user_id          INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider         VARCHAR(32) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    provider_email   VARCHAR(255),
    avatar_url       VARCHAR(255),
    access_token     TEXT,             -- アプリ側で encrypted キャスト
    refresh_token    TEXT,             -- アプリ側で encrypted キャスト
    token_expires_at TIMESTAMPTZ,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider, provider_user_id)
);

-- 9. ログ（ユーザーIDでハッシュパーティション）
CREATE TABLE logs (
    id              BIGSERIAL,
    user_id         INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    app_id          INTEGER     NOT NULL REFERENCES apps(id)  ON DELETE CASCADE,
    log_date        DATE        NOT NULL,
    total_pulls     INTEGER     NOT NULL,
    discharge_items INTEGER     NOT NULL,
    expense_amount  BIGINT      NOT NULL DEFAULT 0,     -- 最小単位の整数、非負
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

-- 推奨インデックス（親に作成可：PostgreSQLが子にも展開）
CREATE INDEX logs_user_app_date_desc_idx ON logs (user_id, app_id, log_date DESC);
CREATE INDEX logs_user_date_desc_idx     ON logs (user_id, log_date DESC);
CREATE INDEX logs_user_expense_idx       ON logs (user_id, expense_amount);

-- パーティション（MODULUS 10 / REMAINDER 0..9）
-- ※ user_id の数値値に対して hashint4 を用いた結果 mod(...,10) で分配されます
DO $$
BEGIN
    FOR i IN 0..9 LOOP
        EXECUTE format(
            'CREATE TABLE logs_p%1$s PARTITION OF logs FOR VALUES WITH (MODULUS 10, REMAINDER %1$s);',
            i
        );
    END LOOP;
END $$;

-- 10. 集計キャッシュ
CREATE TABLE stats_cache (
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,   -- NULL許容（グローバル）
    cache_key   VARCHAR(128) PRIMARY KEY,
    value       JSONB       NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ====================
-- 便利ビュー：通貨少数での金額を返す
-- ====================
CREATE OR REPLACE VIEW logs_with_money AS
SELECT
    l.id,
    l.user_id,
    l.app_id,
    l.log_date,
    l.total_pulls,
    l.discharge_items,
    l.expense_amount,
    a.currency_code,
    c.minor_unit,
    (l.expense_amount::NUMERIC / (10::NUMERIC ^ c.minor_unit)) AS expense_decimal,
    l.drop_details,
    l.tags,
    l.free_text,
    l.images,
    l.tasks,
    l.created_at,
    l.updated_at
FROM logs l
JOIN apps a        ON a.id   = l.app_id
JOIN currencies c  ON c.code = a.currency_code;
