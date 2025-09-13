# Repository Guidelines

本ドキュメントは Pulllog バックエンド（この `backend/` ディレクトリ）向けの貢献ガイドです。構成は Laravel 12 の `stable/` と、軽量 PHP API の `beta/` の二系統です。

## Project Structure & Module Organization
- `stable/`: Laravel アプリ。`app/`, `routes/`, `resources/`, `public/`, `tests/`, `vendor/`。フロント資産は Vite（`package.json`）。
- `beta/`: スタンドアロン API。`hooks/`(各エンドポイント), `libs/`, `tests/`, `schema/`, `vendor/`。
- ルート資料: `README.md`, `controller-map.md`, `pulllog-ER.md`, `pulllog-ddl.sql`。

## Build, Test, and Development Commands
Stable (Laravel):
- セットアップ: `cd stable && composer install && npm ci`
- 環境: `cp .env.example .env && php artisan key:generate`
- 開発起動: `composer dev`（PHP サーバ/キュー/ログ/Vite を並行実行）
- テスト: `composer test` または `php artisan test`

Beta (PHP API):
- セットアップ: `cd beta && composer install && cp .env.sample .env`
- 開発起動: `php -S localhost:8080 -t beta` もしくは `php beta/start_server.php`
- テスト: `vendor/bin/phpunit`
- 静的解析/整形: `vendor/bin/phpstan analyse` / `vendor/bin/phpcs`

## Coding Style & Naming Conventions
- PHP は PSR-12 準拠。Stable では `vendor/bin/pint` で整形。
- 命名: クラス `StudlyCaps`、メソッド/変数 `camelCase`、定数 `UPPER_SNAKE_CASE`。
- Beta のエンドポイント: `hooks/<verb>_<resource>.php`（例: `get_apps.php`, `post_auth_login.php`）。

## Testing Guidelines
- フレームワーク: PHPUnit。ファイル名は `*Test.php`。
- 配置: Stable は `tests/Feature` と `tests/Unit`、Beta は `tests/`。
- カバレッジ: `--coverage-text` 利用可（Xdebug/PCOV 必須）。

## Commit & Pull Request Guidelines
- コミットは命令形で簡潔に（例: "add currencies table", "fix auth flow"）。
- PR には目的/変更点/影響範囲、関連 Issue、動作確認手順（`curl` 例やレスポンス抜粋）を含める。1 PR = 1 トピック。
- CI/テストがすべて通過するまでマージ不可。

## Security & Configuration Tips
- `.env` や秘密情報はコミット禁止。`stable/.env.example` や `beta/.env.sample` を参照。
- DB 初期化: Stable は `init_db.(sh|bat)` または `php artisan migrate`、Beta は `schema/` と `pulllog-ddl.sql` を参照。
- ログには個人情報を残さない（`beta/logs/` はローテーション前提）。

## Agent-Specific Notes
- 本 AGENTS.md は `backend/` 配下に適用。下位に AGENTS.md がある場合は、より深い階層の指示を優先してください。

