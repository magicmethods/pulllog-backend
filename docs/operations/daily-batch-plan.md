# 日次バッチ（バックアップ/サマリ）要件定義・機能設計

## 目的
- 信頼できる日次バックアップの取得と保持。
- 運用可視化のため、登録ユーザー/登録アプリのサマリを自動生成。

## スコープ/前提
- 実行: 毎日 03:30 JST（本番のみ）。
- DB: PostgreSQL。フルダンプ（`pg_dump -Fc`）。
- 保存: `storage/app/backups/YYYYMMDD/`。gzip + AES-256 暗号化。
- 保持: 14日（世代数/日次保持）。
- サマリ: `storage/app/reports/YYYYMMDD/` に CSV/Markdown。
- 通知: 成功/失敗とも `admin@pulllog.net` へメール（`.env: BATCH_NOTIFY_EMAIL`）。

## サマリ仕様
- ユーザー: 総数、当日新規、30日アクティブ（`UserSession` の直近30日存在）。
- アプリ: 総数、当日新規、トップN（直近30日ログ件数）N=10。
- 区分集計: ロケール別（`users.locale`）、プラン別（`plans`/`users.plan_id`）。
- 形式: `summary_YYYYMMDD.csv` / `summary_YYYYMMDD.md`。

## 実装方針（Laravel 12）
- コマンド: `db:backup`、`report:daily-summary` を `app/Console/Commands` に追加。
- スケジュール: `routes/console.php` で 03:30 JST に2コマンドを順次実行。
- バックアップ: `Process` で `pg_dump` 実行 → `.dump` を gzip → OpenSSL(AES-256-CBC)で暗号化。
- 変数: `BACKUP_ENCRYPTION_KEY`、`PG_DUMP_PATH`（既定 `pg_dump`）。
- 保持: 14日より古いディレクトリを削除（安全に `YYYYMMDD` パターンのみ）。
- サマリ: Eloquent で集計、CSV/MD を `reports/` 配下に保存。
- 通知: `Mail` で成功/失敗を送信（件名に日付、本文に保存先/件数、失敗はエラーログ付与）。

## 環境変数
- `PG_DUMP_PATH`（例: `pg_dump`）
- `BACKUP_ENCRYPTION_KEY`（未設定なら暗号化スキップ）
- `RETENTION_DAYS`（例: `14`）
- `REPORT_TOP_N`（例: `10`）
- `BATCH_NOTIFY_EMAIL`（例: `admin@pulllog.net`）

## 運用/再実行
- 手動: `php artisan db:backup --date=YYYY-MM-DD --dry-run`、`php artisan report:daily-summary --date=...`。
- リトライ: 失敗時は3回（間隔1分）を内部リトライ。最終失敗でメール通知。

## セキュリティ
- 鍵は `.env` のみで管理。バックアップファイル名に秘匿情報を含めない。
- サマリは個人特定情報を含めない（集計のみ）。

## 受け入れ基準（抜粋）
- 03:30 JST に2ジョブが自動実行され、14日分の世代が維持される。
- レポート/バックアップの保存・通知メールが確認できる（成功/失敗）。
