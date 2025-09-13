# 設定/セキュリティ/保持ポリシーを整備する

## 背景
機能の安全な運用には設定値と秘匿情報の管理、保持/削除ポリシーの明文化が必要。

## スコープ
- `.env.example` へ `BACKUP_ENCRYPTION_KEY`, `PG_DUMP_PATH`, `REPORT_TOP_N` を追記
- バックアップ/レポートの保存パスと命名規則のドキュメント化
- 保持日数（14日）の設定化
- `.gitignore`/ドキュメントの整備

## 非スコープ
- KMS/Secret Manager 等の導入

## 受け入れ基準
- 必要な環境変数が `.env.example` に存在
- ドキュメントに保持/削除手順と注意点が記載

## タスク
- [ ] `.env.example` 追記
- [ ] ドキュメント更新（`docs/daily-batch/README.md`）
- [ ] 既存 `.gitignore` と整合性確認
