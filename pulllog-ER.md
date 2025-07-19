# PullLog ER

```mermaid
erDiagram
    users      ||--o{ user_apps: "has"
    users      ||--o{ logs: "has"
    users      ||--o{ auth_tokens: "has"
    users      ||--o{ user_sessions: "has"
    users      ||--o{ stats_cache: "has"
    users      }|--|| plans: "plan"
    apps       ||--o{ user_apps: "has"
    apps       ||--o{ logs: "has"
    logs       ||--o{ stats_cache: "has"
    plans      ||--o{ users: "plan"
```

```mermaid
erDiagram
    Users ||--o{ UserApps: "has"
    Users {
        SERIAL id
        VARCHAR email
        VARCHAR password_hash
        VARCHAR name
        VARCHAR avatar_url
        VARCHAR[] roles
        INTEGER plan_id
        TIMESTAMPTZ plan_expiration
        VARCHAR language
        theme theme
        VARCHAR home_page
        TIMESTAMPTZ last_login
        VARCHAR last_login_ip
        VARCHAR last_login_ua
        BOOLEAN is_deleted
        BOOLEAN is_verified
        INTEGER[] unread_notices
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
    }
    UserApps {
        SERIAL id
        INTEGER user_id
        INTEGER app_id
        TIMESTAMPTZ created_at
    }
    Users ||--o{ Logs: "has"
    Logs {
        BIGSERIAL id
        INTEGER user_id
        INTEGER app_id
        DATE log_date
        INTEGER total_pulls
        INTEGER discharge_items
        INTEGER expense
        drop[] drop_details
        TEXT[] tags
        TEXT free_text
        TEXT[] images
        JSONB tasks
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
    }
    Users ||--o{ AuthTokens: "has"
    AuthTokens {
        SERIAL id
        INTEGER user_id
        VARCHAR token
        token_type type
        VARCHAR code
        BOOLEAN is_used
        TIMESTAMPTZ expires_at
        TIMESTAMPTZ created_at
    }
    Users ||--o{ UserSessions: "has"
    Users }|--|| Plans: "plan"
    Plans ||--o{ Users: "plan"
    Apps ||--o{ UserApps: "has"
    Apps {
        SERIAL id
        VARCHAR app_key
        VARCHAR name
        VARCHAR url
        TEXT description
        VARCHAR currency_unit
        VARCHAR date_update_time
        BOOLEAN sync_update_time
        BOOLEAN pity_system
        INTEGER guarantee_count
        definition[] rarity_defs
        definition[] marker_defs
        JSONB task_defs
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
    }
    Apps ||--o{ Logs: "has"
    Logs ||--o{ StatsCache: "has"
    Users ||--o{ StatsCache: "has"
```

```mermaid
erDiagram
    entity Apps {
        SERIAL id
        VARCHAR app_key
        VARCHAR name
        VARCHAR url
        TEXT description
        VARCHAR currency_unit
        VARCHAR date_update_time
        BOOLEAN sync_update_time
        BOOLEAN pity_system
        INTEGER guarantee_count
        definition[] rarity_defs
        definition[] marker_defs
        JSONB task_defs
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
    }
```

```mermaid
erDiagram
    entity UserSessions {
        VARCHAR csrf_token PK
        INTEGER user_id FK
        VARCHAR email
        TIMESTAMPTZ created_at
        TIMESTAMPTZ expires_at
    }
    entity StatsCache {
        user_id
        cache_key
        value
        created_at
        updated_at
    }
    entity Plans {
        SERIAL id PK
        VARCHAR name UNIQUE
        TEXT description
        INTEGER max_apps
        INTEGER max_app_name_length
        INTEGER max_app_desc_length
        INTEGER max_log_tags
        INTEGER max_log_tag_length
        INTEGER max_log_text_length
        INTEGER max_logs_per_app
        INTEGER max_storage_mb
        INTEGER price_per_month
        BOOLEAN is_active
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
    }
```
