# PullLog Backend
個人のガチャ履歴を記録・管理するWebアプリ「PullLog」のバックエンドリポジトリです。  
バックエンドはAPIエントリポイントとしてフロントエンドとデータベースとのデータの中継を担います。  
本アプリの正式版は Laravel + PostgreSQL を中心技術として構築します。  
初期開発用のモック環境としては [MockAPI-PHP](https://github.com/ka215/MockAPI-PHP) を使用しています。

---

## 目次

- [主な特徴](#主な特徴)
- [技術スタック](#技術スタック)
- [テーブル構成](#テーブル構成)
- [ER図](#ER図)
- [モック環境](#モック環境)
- [ライセンス](#ライセンス)
- [コントリビューション](#コントリビューション)
- [関連リンク](#関連リンク)

---

## 主な特徴

- エンドポイントのリクエストに対してJSONレスポンスを応答する
- リクエスト毎のトークン認証・データベース処理
- 現状は管理画面等のUIはなし
- OpenAPI 3.0のスキーマ駆動型の実装

---

## 技術スタック

- **PHP**: PHP v8.3 (開発環境は v8.4.2)
- **フレームワーク**: Laravel v12.20.0
- **データベース**: PostgreSQL v14.13 (開発環境は v17.4)
- **OpenAPI 連携**: openapi-generator-cli v7.14.0 (※ 要JDK v11.x以上)
- **モック環境**: MockAPI-PHP v1.3.1
- **シリアル化**: crell/serde v1.5.0 (※ openapi-generator-cli の生成コードにて使用)

---

## テーブル構成

| テーブル名     | 用途・説明           | 主なカラム                                                         |
|----------------|----------------------|--------------------------------------------------------------------|
| `plans`        | 契約プラン管理       | id (PK), name, max_apps, ...                                       |
| `users`        | ユーザー管理         | id (PK), email (UQ), roles, plan_id (FK), ...                      |
| `apps`         | アプリケーション管理 | id (PK), app_key (UQ), name, ...                                   |
| `user_apps`    | ユーザー・アプリ管理（リレーション） | id (PK), user_id (FK), app_id (FK), ...            |
| `logs`         | 日次ログ（履歴）管理 | id (PK), user_id (PK,FK), app_id (FK), log_date, total_pulls, ...  |
| `auth_tokens`  | 認証トークン管理     | id (PK), user_id (FK), token (UQ), type, ...                       |
| `user_sessions`| ユーザーセッション（CSRFトークン）管理 | csrf_token (PK), user_id (FK), email, ...        |
| `stats_cache`  | 統計データ（キャッシュ）管理 | cache_key (PK), user_id (FK), value, ...                   |

> **注**:  
> - (PK) = 主キー  
> - (UQ) = ユニーク制約  
> - (FK) = 外部キー  
> - 各テーブルの詳細設計や全カラム・型は [pulllog-ddl.sql](https://github.com/magicmethods/pulllog-backend/blob/main/pulllog-ddl.sql) を参照

※ pulllog-ddl.sql はあくまでスキーマ確認用です。手動DDLとして使用するのは**NG**です。DBマイグレーションは Laravel の `artisan maigrate` を使ってください。

---

## ER図

主要なエンティティ（テーブル）とリレーションの概要図です。  
属性値まで含む詳細ER図は [pulllog-ER.md](https://github.com/magicmethods/pulllog-backend/blob/main/pulllog-ER.md) をご覧ください。

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

---

## モック環境

モックシステムは `beta/` ディレクトリに格納しています。
稼働するにはPHPの実行環境下にて `beta/` へ移動後、下記のコマンドを実行してください。  
※ PHPが稼働中のサーバ環境の場合、簡易サーバ起動は不要です。

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

