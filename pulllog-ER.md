# PullLog ER

```mermaid
erDiagram
    plans {
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
    users {
        SERIAL id PK
        VARCHAR email UNIQUE
        VARCHAR password_hash
        VARCHAR name
        VARCHAR avatar_url
        VARCHAR[] roles
        INTEGER plan_id FK
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
    apps {
        SERIAL id PK
        VARCHAR app_key UNIQUE
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
    user_apps {
        SERIAL id PK
        INTEGER user_id FK
        INTEGER app_id FK
        TIMESTAMPTZ created_at
    }
    logs {
        BIGSERIAL id PK
        INTEGER user_id FK
        INTEGER app_id FK
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
    auth_tokens {
        SERIAL id PK
        INTEGER user_id FK
        VARCHAR token UNIQUE
        token_type type
        VARCHAR code
        BOOLEAN is_used
        TIMESTAMPTZ expires_at
        TIMESTAMPTZ created_at
    }
    user_sessions {
        VARCHAR csrf_token PK
        INTEGER user_id FK
        VARCHAR email
        TIMESTAMPTZ created_at
        TIMESTAMPTZ expires_at
    }
    stats_cache {
        INTEGER user_id FK
        VARCHAR cache_key PK
        JSONB value
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
    }

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
