# 日次サマリレポートを実装する

## 背景
運用可視化のため、ユーザー/アプリの主要指標を毎日集計し保存する。

## スコープ
- `php artisan report:daily-summary` コマンド実装
- 集計: ユーザー総数/当日新規/30日アクティブ、アプリ総数/当日新規、トップN（直近30日ログ件数）N=10
- 区分: ロケール別、プラン別
- 出力: CSV と Markdown を `storage/app/reports/YYYYMMDD/` に保存

## 非スコープ
- 個票/PII を含む詳細出力

## 受け入れ基準
- 指標値が正しい（テスト用固定データで検証）
- CSV/MD のフォーマットが仕様どおり

## タスク
- [ ] `app/Console/Commands/ReportDailySummaryCommand.php` を追加
- [ ] 集計ロジック/DTO/書き出しユーティリティ実装
- [ ] 単体テスト（SQLiteメモリ/シード）
- [ ] ドキュメント更新
