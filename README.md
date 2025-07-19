# PullLog Backend
個人のガチャ履歴を記録・管理するWebアプリ「PullLog」のバックエンドリポジトリです。  
バックエンドはAPIエントリポイントとしてフロントエンドとデータベースとのデータの中継を担います。  
本アプリの正式版は Laravel + PostgreSQL + SQLite を中心技術として構築します。初期開発用のモック環境としては MockAPI-PHP を使用します。

---

## 目次

- [ER図](#ER図)
- [モック環境](#モック環境)
- [ライセンス](#ライセンス)
- [コントリビューション](#コントリビューション)
- [関連リンク](#関連リンク)

---

## ER図

```mermaid
erDiagram
    plans {
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
    users {
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
    apps {
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
    user_apps {
        id
        user_id
        app_id
        created_at
    }
    logs {
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
    auth_tokens {
        id
        user_id
        token
        type
        code
        is_used
        expires_at
        created_at
    }
    user_sessions {
        csrf_token
        user_id
        email
        created_at
        expires_at
    }
    stats_cache {
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

---

## モック環境

モックシステムは `beta/` ディレクトリに格納しています。
稼働するにはPHPの実行環境下にて `beta/` へ移動後、下記のコマンドを実行してください。

```bash
composer install

php ./start_server.php
```

PHPの簡易サーバが起動すると `http://localhost:3030/beta` をバックエンドのAPIサーバとして利用できます。  
フロントエンド側の `.env.local` にて、バックエンドAPIのURLをモック側に指定してから `pnpm dev` を実行してください。

```env
API_BASE_URL=http://localhost:3030/beta
```

※ モック環境ではメール認証系の処理を省略してあるため、アカウント登録時等にメール送信が行われません。

---

## ライセンス

MAGIC METHODS に帰属します。

---

## コントリビューション

関係各位のPull Request・Issue歓迎です。
設計や方針の議論はDiscussionsまたはIssueで行ってください。

---

## 関連リンク

- [PullLog フロントエンドリポジトリ](https://github.com/magicmethods/pulllog-frontend)
- [PullLog API仕様書](https://github.com/magicmethods/pulllog-contract)
- ドキュメント

