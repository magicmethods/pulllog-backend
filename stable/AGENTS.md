# Repository Guidelines

本ドキュメントは Pulllog の `backend/stable/`（Laravel 12）向けコントリビュータガイドです。詳細なコードベースの構造と API の挙動は「stable/ Laravel コードベース要約」を参照してください（`STABLE_CODEBASE_SUMMARY.md`）。

## Project Structure & Module Organization
- 主要ディレクトリ: `app/`, `routes/`, `config/`, `database/`, `resources/`, `public/`, `tests/`。
- 参考資料: `.env.example`, `api-schema.yaml`。
- コードベースの詳細: `STABLE_CODEBASE_SUMMARY.md`。

## Build, Test, and Development Commands
- セットアップ: `composer install && npm ci`
- 環境準備: `cp .env.example .env && php artisan key:generate`
- 開発起動: `composer dev`（`serve`/`queue:listen`/`pail`/`vite` を並行）
- テスト: `composer test`（`phpunit.xml` は SQLite メモリ設定）

## Coding Style & Naming Conventions
- PHP: PSR-12、スペース4。整形は `vendor/bin/pint`。
- 命名: クラス `StudlyCaps`、メソッド/変数 `camelCase`、定数 `UPPER_SNAKE_CASE`。
- ルーティングは `routes/api.php` を基点（`config('api.base_uri')` 配下）。

## Testing Guidelines
- フレームワーク: PHPUnit。配置は `tests/Unit` / `tests/Feature`、ファイル名は `*Test.php`。
- 実行: `composer test`。必要に応じ `--coverage-text`（Xdebug/PCOV）。
- テスト用 DB: SQLite メモリ（`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`）。

## Commit & Pull Request Guidelines
- コミットは命令形・短文（例: "fix auth flow", "add currencies table"）。
- PR には目的/変更点/影響範囲、関連 Issue、動作確認手順（`curl` 例やレスポンス抜粋）を明記。1 PR = 1 トピック。
- CI/テストが全て通過するまでマージ不可。

## Security & Configuration Tips
- 秘密情報（`.env` 等）はコミット禁止。
- DB 初期化: `php artisan migrate`（または `init_db.(sh|bat)`）。
- API 連携: `API_BASE_URI` と `API_KEY` を設定。ログへ個人情報を残さない。

<<<<<<< Updated upstream
=======
## API Contract
- API スキーマの正本は `contract/api-schema.yaml`（contract ワークスペース管理）。
- エンドポイントを追加・変更する際は必ず `contract/api-schema.yaml` を参照すること。

## Docs / Issue 運用
- stable の設計メモや運用手順は `../docs/`（backend/docs）を正本として管理する。
- GitHub Issue は `.github/ISSUE_TEMPLATE/` のテンプレートを利用して起票する。
- 一時的な作業メモは `../.codex/` を利用する。

## Others
- `.codex/` を codex CLI で作業する際のテンポラリディレクトリとして利用する。作業用の一時ファイルやバグレポート、Issueのひな形、補足ドキュメント等を自由に出力して構いません。
>>>>>>> Stashed changes
