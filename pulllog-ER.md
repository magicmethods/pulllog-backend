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
        SERIAL id PK
        email
        password_hash
        name
        avatar_url
        roles
        plan_id
        plan_expiration
        language
        theme
        home_page
        last_login
        last_login_ip
        last_login_ua
        is_deleted
        is_verified
        unread_notices
        created_at
        updated_at
    }
    UserApps {
        id
        user_id
        app_id
        created_at
    }
    Users ||--o{ Logs: "has"
    Logs {
        id
        user_id
        app_id
        log_date
        total_pulls
        discharge_items
        expense
        drop_details
        tags
        free_text
        images
        tasks
        created_at
        updated_at
    }
    Users ||--o{ AuthTokens: "has"
    AuthTokens {
        id
        user_id
        token
        type
        code
        is_used
        expires_at
        created_at
    }
    Users ||--o{ UserSessions: "has"
    Users }|--|| Plans: "plan"
    Plans ||--o{ Users: "plan"
    Plans {
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
    Apps ||--o{ UserApps: "has"
    Apps {
        id
        app_key
        name
        url
        description
        currency_unit
        date_update_time
        sync_update_time
        pity_system
        guarantee_count
        rarity_defs
        marker_defs
        task_defs
        created_at
        updated_at
    }
    Apps ||--o{ Logs: "has"
    Logs ||--o{ StatsCache: "has"
    Users ||--o{ StatsCache: "has"
    UserSessions {
        csrf_token
        user_id
        email
        created_at
        expires_at
    }
    StatsCache {
        user_id
        cache_key
        value
        created_at
        updated_at
    }

```
