# 日次DBバックアップを実装する

## 背景
運用上、毎日フルバックアップの取得と14日保持が必要。障害時の迅速な復旧に備える。

## スコープ
- `php artisan db:backup` コマンド実装
- `pg_dump -Fc` で取得 → gzip → AES-256-CBC で暗号化
- 保存先: `storage/app/backups/YYYYMMDD/`
- 保持: 14日超の世代を削除
- 失敗時のエラーハンドリングと終了コード

## 非スコープ
- 差分/増分バックアップ

## 受け入れ基準
- コマンド成功で暗号化済みファイルが生成される
- ログに実行概要（サイズ/経過時間/保存先）が残る
- 14日超の世代が削除される

## タスク
- [ ] `app/Console/Commands/DbBackupCommand.php` を追加
- [ ] `env` 変数: `BACKUP_ENCRYPTION_KEY`, `PG_DUMP_PATH`
- [ ] gzip + OpenSSL 実装、例外処理/タイムアウト
- [ ] 保持削除ロジック（`YYYYMMDD` パターンのみ対象）
- [ ] 単体テスト（Processモック/ドライラン）
- [ ] ドキュメント更新（`.env.example`/README）
