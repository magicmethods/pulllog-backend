# サービス運用概要

この文書は、PullLog backend subproject が管理するサービス運用情報の正本です。  
公開文書では概要のみを扱い、本ドキュメントおよび関連運用文書を参照します。

## 対象範囲

- stable/ の本番運用
- デプロイ、監視、障害対応、バックアップ
- API 運用に関わる定常的な保守作業

beta/ のローカルモック運用は対象外です。mock 環境のセットアップやローカル開発手順は backend ルート README を参照してください。

## ローカル開発 / E2E 運用

- backend/stable の local-dev と e2e の運用差分は [local-development-runtime.md](local-development-runtime.md) を正本とします。
- 通常開発は local-dev レーン、Playwright は e2e レーンを利用し、両者を混在させません。
- `e2e:prepare` は E2E 用 DB の初期化を含み、`e2e:serve` は起動のみです。

## デプロイとリリース

- 本番向けのリリース手順とロールバック手順は [deploy-and-release.md](deploy-and-release.md) に集約します。
- リリース処理は `scripts/pulllog_release.sh`、切り戻しは `scripts/pulllog_rollback.sh` を正本の自動化手段とします。
- 変更反映の単位、適用前確認、失敗時の戻し方は deploy-and-release 側で管理します。

## 運用ブランチ方針

- `main`: production-ready code
- `staging`: staging / mock testing environment

ブランチ戦略の詳細な運用判断は backend 側で管理し、公開文書では固定値として再定義しません。

## 監視と可観測性

- アプリケーションログは backend 環境側で管理します。
- 異常検知、アラート、性能監視の具体的な設定値や連携先は backend 側で管理します。
- 稼働確認には `GET /api/v1/dummy` を利用できますが、外部公開文書での監視設計の正本にはしません。

## バックアップ方針

- データベースは日次の論理バックアップと週次スナップショットを基本とします。
- アプリケーション資産とリポジトリは別系統で保全します。
- 保持期間や具体的な保存先の運用値は backend 側で管理し、変更時は本ドキュメントを更新します。

現行の基準:

- 日次 logical backup: 30 日保持
- 週次 snapshot: 3 か月保持
- アセット類: 週次バックアップ

## 障害対応

- 検知: ログ、監視、ユーザー報告
- 初動: 影響範囲の把握、必要に応じたデプロイ停止またはロールバック
- 連絡: 公開向けの案内は別途運用チャネルで行う

障害時の公開向け説明内容は pulllog-docs 側で管理せず、backend 側の運用判断を正とします。

## セキュリティ運用

- 依存関係更新、鍵管理、ローテーション方針は backend 側で管理します。
- API key rotation の実施有無や手順は backend 側の運用事項です。
- 脆弱性報告窓口自体は公開文書の SECURITY.md を参照します。

## 関連文書

- `../../README.md`
- `../architecture/overview.md`
- `./deploy-and-release.md`