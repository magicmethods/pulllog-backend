-- ====================
-- 型定義
-- ====================
DO $$
BEGIN
    -- theme型
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'theme') THEN
        CREATE TYPE theme AS ENUM ('light', 'dark');
    END IF;
END$$;

DO $$
BEGIN
    -- token_type型
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'token_type') THEN
        CREATE TYPE token_type AS ENUM ('signup', 'reset', 'remember');
    END IF;
END$$;

DO $$
BEGIN
    -- definition型
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
    -- drop型
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'drop') THEN
        CREATE TYPE drop AS (
            rarity      VARCHAR(64),
            name        VARCHAR(64),
            marker      VARCHAR(64)
        );
    END IF;
END$$;

-- ====================
-- テーブル初期化（DROPは依存関係を逆順に）
-- ====================

DROP TABLE IF EXISTS stats_cache      CASCADE;
DROP TABLE IF EXISTS logs             CASCADE;
DROP TABLE IF EXISTS user_sessions    CASCADE;
DROP TABLE IF EXISTS auth_tokens      CASCADE;
DROP TABLE IF EXISTS user_apps        CASCADE;
DROP TABLE IF EXISTS apps             CASCADE;
DROP TABLE IF EXISTS users            CASCADE;
DROP TABLE IF EXISTS plans            CASCADE;

-- ====================
-- DDL
-- ====================

-- 1. プラン管理
CREATE TABLE plans (
    id                  SERIAL PRIMARY KEY,
    name                VARCHAR(64) NOT NULL UNIQUE,      -- プラン名 (Free, Standard, Premium)
    description         TEXT,
    max_apps            INTEGER NOT NULL DEFAULT 5,       -- 登録可能なアプリ数上限
    max_app_name_length INTEGER NOT NULL DEFAULT 30,      -- 登録アプリ名の文字数上限
    max_app_desc_length INTEGER NOT NULL DEFAULT 400,     -- 登録アプリの説明文の文字数上限
    max_log_tags        INTEGER NOT NULL DEFAULT 5,       -- 日次ログに追加可能なタグ数上限
    max_log_tag_length  INTEGER NOT NULL DEFAULT 22,      -- 日次ログのタグ名の文字数上限
    max_log_text_length INTEGER NOT NULL DEFAULT 250,     -- 日次ログのアクティビティの文字数上限
    max_logs_per_app    INTEGER NOT NULL DEFAULT -1,      -- 1アプリあたりの日次ログ登録数上限（-1: 無制限）
    max_storage_mb      INTEGER NOT NULL DEFAULT 100,     -- ストレージ容量（MB単位）
    price_per_month     INTEGER NOT NULL DEFAULT 0,       -- 月額料金（円単位など）
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. ユーザー管理
CREATE TABLE users (
    id              SERIAL PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    name            VARCHAR(64) NOT NULL,   -- display_name
    avatar_url      VARCHAR(255),
    roles           VARCHAR[] NOT NULL,     -- ユーザーロール（admin, userなど）将来的に多段階・複数ロール運用も可能
    plan_id         INTEGER NOT NULL REFERENCES plans(id) ON DELETE RESTRICT,
    plan_expiration TIMESTAMPTZ NOT NULL,
    language        VARCHAR(10) NOT NULL,   -- 現状 en, ja, zh の3種のみだが、locale値の **-** や **-****-** に対応可能
    theme           theme DEFAULT 'light',
    home_page       VARCHAR(20) NOT NULL,
    last_login      TIMESTAMPTZ,
    last_login_ip   VARCHAR(128),
    last_login_ua   VARCHAR(255),
    is_deleted      BOOLEAN NOT NULL DEFAULT FALSE,
    is_verified     BOOLEAN NOT NULL DEFAULT FALSE,
    unread_notices  INTEGER[],  -- 将来的にNOTIFY管理テーブルを独立させるまでの暫定
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 3. アプリ管理
CREATE TABLE apps (
    id                  SERIAL PRIMARY KEY,
    app_key             VARCHAR(64) NOT NULL UNIQUE,  -- ULID形式
    name                VARCHAR(128) NOT NULL,
    url                 VARCHAR(255),
    description         TEXT,
    currency_unit       VARCHAR(20),
    date_update_time    VARCHAR(5) NOT NULL,
    sync_update_time    BOOLEAN DEFAULT FALSE,
    pity_system         BOOLEAN DEFAULT FALSE,
    guarantee_count     INTEGER NOT NULL DEFAULT 0,
    rarity_defs         definition[],
    marker_defs         definition[],
    task_defs           JSONB,  -- 構造未定
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 4. ユーザー✕アプリ（マッピング・リレーション）
CREATE TABLE user_apps (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    app_id          INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, app_id)
);

-- 5. 認証/リセット/Rememberトークン等
CREATE TABLE auth_tokens (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token           VARCHAR(255) NOT NULL UNIQUE,
    type            token_type NOT NULL,
    code            VARCHAR(6),
    is_used         BOOLEAN DEFAULT FALSE,
    expires_at      TIMESTAMPTZ NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 6. セッション（CSRF管理含む）
CREATE TABLE user_sessions (
    csrf_token      VARCHAR(255) PRIMARY KEY,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email           VARCHAR(255) NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL,
    expires_at      TIMESTAMPTZ NOT NULL
);

-- 7. ログ（ユーザーごとにパーティション）
CREATE TABLE logs (
    id              BIGSERIAL,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    app_id          INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
    log_date        DATE NOT NULL,
    total_pulls     INTEGER NOT NULL,
    discharge_items INTEGER NOT NULL,
    expense         INTEGER DEFAULT 0,
    drop_details    drop[],           -- 詳細アイテムリスト
    tags            TEXT[],
    free_text       TEXT,
    images          TEXT[],
    tasks           JSONB,            -- タスク完了情報（暫定仕様）
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, id),
    UNIQUE (user_id, app_id, log_date)
) PARTITION BY HASH (user_id);

-- 8. （オプション）集計キャッシュ
CREATE TABLE stats_cache (
    user_id         INTEGER REFERENCES users(id) ON DELETE CASCADE, -- 複数ユーザーに対応する場合はNULL許容
    cache_key       VARCHAR(128) PRIMARY KEY,
    value           JSONB NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ====================
-- ログパーティションの作成
-- - ユーザーIDの下2桁で分割 0〜9:10分割, 必要に応じて増やせる
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
-- 初期データ投入（プラン）
-- ====================

INSERT INTO plans (name, description, max_apps, max_log_tags, max_storage_mb, price_per_month, is_active)
    VALUES
    ('Free', 'Free plan limited to minimal usage.', 5, 3, 100, 0, TRUE),
    ('Standard', 'Standard plan for comfortable use with no ads.', 10, 5, 300, 480, TRUE),
    ('Premium', 'Premium plans give you unlimited access to our advanced features.', 50, 10, 1024, 980, TRUE)
    ON CONFLICT (name) DO NOTHING;
