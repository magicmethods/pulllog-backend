# デプロイ・ロールバック運用

本ドキュメントは backend/scripts の運用手順をまとめたものです。

## 対象スクリプト

- scripts/pulllog_release.sh
- scripts/pulllog_rollback.sh

## リリース手順（推奨）

```bash
./scripts/pulllog_release.sh --migrate 20250927-1
```

主なオプション:

- --ref <ref>: デプロイ対象ブランチ/タグ/コミットを指定
- --frontend: stable の npm ビルドを実行（デフォルトはスキップ）
- --skip-frontend: npm ビルドを明示的にスキップ
- --force: 同名リリースディレクトリを確認なしで置換

## ロールバック手順（推奨）

```bash
./scripts/pulllog_rollback.sh
```

特定リリースへ戻す場合:

```bash
./scripts/pulllog_rollback.sh 20250920-1
```

## 運用上の注意

- 実行前に shared/.env と shared/storage の権限を確認する
- 実行後は .current_release と pulllog_current のリンク先を確認する
- マイグレーション有無はリリース前に必ず判断する
- 失敗時はログと終了コードを確認し、必要なら即時ロールバックする

## 参照

- 詳細な背景説明と手動手順: release.md
