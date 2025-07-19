# PullLog ER

```mermaid
erDiagram
    entity plans {
        id
        name
        description
        max_apps
        max_app_name_length
        max_app_desc_length
        max_log_tags
        max_log_tag_length
        max_log_text_length
        max_logs_per_app
        max_storage_mb
        price_per_month
        is_active
        created_at
        updated_at
    }

    entity users {
        id
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

    entity apps {
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

    entity user_apps {
        id
        user_id
        app_id
        created_at
    }

    entity logs {
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

    entity auth_tokens {
        id
        user_id
        token
        type
        code
        is_used
        expires_at
        created_at
    }
    
    entity user_sessions {
        csrf_token
        user_id
        email
        created_at
        expires_at
    }

    entity stats_cache {
        user_id
        cache_key
        value
        created_at
        updated_at
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
