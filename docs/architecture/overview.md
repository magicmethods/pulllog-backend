# バックエンド アーキテクチャ概要

## 対象範囲

このドキュメントは backend ワークスペースのうち、以下を対象とする。

- stable/: Laravel 12 API 本体
- scripts/: リリース/ロールバックスクリプト

beta/ は別系統のため、本整備の対象外。

## システム構成

```text
Client / Frontend
  -> API Gateway (api.pulllog.net)
     -> stable/public (Laravel 12)
        -> PostgreSQL
        -> storage (shared)
```

## ディレクトリ責務

| パス | 役割 |
|---|---|
| stable/app | コントローラ、サービス、ドメインロジック |
| stable/routes | API/Console/Web ルーティング |
| stable/config | 環境変数と実行設定 |
| stable/database | migration, seeder, factory |
| stable/tests | Feature/Unit テスト |
| scripts | リリース運用の自動化 |
| docs | 長期参照ドキュメント |

## API 実装方針

- stable のエンドポイントは routes/api.php と app/Http/Controllers 配下で実装する
- 仕様変更時は contract/api-schema.yaml を正本として先に確認する
- レスポンス構造は契約スキーマ準拠を原則とする

## リリース運用方針

- scripts/pulllog_release.sh でリリース処理を定型化する
- scripts/pulllog_rollback.sh で切り戻しを定型化する
- 手順詳細は docs/operations/deploy-and-release.md に一本化する
