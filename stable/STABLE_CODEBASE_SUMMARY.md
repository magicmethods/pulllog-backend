# stable/ Laravel コードベース要約

Pulllog の Laravel 12（PHP 8.2）アプリです。API 中心のバックエンドで、Vite/Tailwind で最小限の資産をビルドします。主な依存は `laravel/sanctum`, `doctrine/dbal`, `intervention/image`, `reliese/laravel`。OpenAPI 仕様（`api-schema.yaml`）からサーバスタブを `generated/` に生成できます。

## ディレクトリ概観
- `app/` コア実装：
  - `Http/Controllers/Api/*` 認証・アプリ・ログ・統計・ユーザ等の REST。
  - `Http/Middleware/` `AuthApiKey`（`x-api-key` 検証）, `AuthCsrfToken`（`x-csrf-token` 検証）, `DemoGuard`（デモ書込抑止）。
  - `Models/` `User`, `App`, `Log`, `LogWithMoney`, `StatsCache` ほか。
  - `Services/` 認証メール、Google OAuth、ロケール解決など。
- `routes/` `api.php`（主要エンドポイント）, `web.php`（最小ビュー）。
- `config/` `api.php`（`API_BASE_URI`, `API_KEY`）, `demo.php` ほか。
- `database/` マイグレーション/シーダ。現状 `migrations=13`, `seeders=6`。
- `generated/` OpenAPI 生成物（`routes.php`, モデル群）。

## ルーティング/ミドルウェア
- ルート基底: `config('api.base_uri')`（例 `.env: API_BASE_URI=/v1`）。
- 既定で API に `auth.apikey`, `demo.guard`, `auth.csrf` を付与（`bootstrap/app.php`）。必要に応じ `withoutMiddleware` で調整。
- 主なグループ：
  - `auth/*`（登録/検証/ログイン/CSRF更新/Google交換/ログアウト）
  - `apps/*`（CRUD）, `logs/*`（一覧/日次/インポート）, `stats/*`, `user/*`, `currencies/*`。

例: `curl -H "x-api-key: <key>" -H "x-csrf-token: <token>" http://localhost:3030/v1/apps`

## 実行/テスト
- 開発: `composer dev`（serve/queue/pail/vite を並行起動）。
- ビルド資産: `npm run dev|build`（Vite）。
- テスト: `composer test`（`phpunit.xml` は SQLite メモリ、`app/` をカバレッジ対象）。

## 環境変数の要点
- `API_BASE_URI`（例 `/v1`）、`API_KEY`（必須）。
- メール: `MAIL_MAILER=log` 既定。CORS 設定は `CORS_*`。
- デモ制御: `DEMO_EMAIL`, `DEMO_USER_IDS`（`DemoGuard` が書込を 204 で抑止）。
