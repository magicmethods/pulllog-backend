# Backend Docs Index

このディレクトリは、バックエンド（特に stable/ と scripts/）の開発・運用で継続参照するドキュメントを置く場所です。

## ディレクトリ構成

```text
docs/
├── README.md                                   # このファイル
├── architecture/
│   └── overview.md                             # 構成・責務・API実装方針
│   └── feature-development-workflow.md         # stable 機能開発の agent workflow
├── features/
│   ├── _templates/
│   │   ├── README.md                           # feature 文書テンプレートの説明
│   │   ├── workflow-notes.md                   # Orchestrator 用の進行メモ
│   │   ├── feature-plan.md                     # Architect 用の設計ひな形
│   │   ├── contract-impact.md                  # Contract 整理ひな形
│   │   ├── implementation-notes.md            # Implementer 用の検証メモ
│   │   └── review-notes.md                     # Reviewer 用のレビュー結果
│   └── gallery/
│       ├── upload-ticket-spec.md               # BE-GALLERY-UPLOAD-TICKET 仕様
│       └── public-short-url-plan.md            # 公開短縮URL実装計画
├── integrations/
│   └── frontend/
│       └── gallery-integration-notes.md        # フロント連携メモ
└── operations/
    ├── deploy-and-release.md                   # デプロイ/ロールバック運用手順
    ├── local-development-runtime.md            # local-dev / e2e の運用レーン整理
    ├── service-operations-overview.md          # サービス運用の正本概要
    ├── daily-batch-plan.md                     # 日次バッチ設計
    ├── gallery-manual-test-report-notes.md     # 手動テスト記録
    └── gallery-bug-report-notes.md             # 不具合報告メモ
```

## 役割

| ディレクトリ | 置くもの |
|---|---|
| architecture/ | システム構成、責務分離、API実装原則 |
| features/ | 機能ごとの実装計画、仕様、設計判断 |
| integrations/ | フロントエンド/契約との連携仕様 |
| operations/ | リリース、運用、障害対応、ローカル起動、テスト記録 |

## Agent Workflow

- stable の機能開発は architecture/feature-development-workflow.md を参照する
- backend の custom agent は orchestrator-first で使い、通常の入口は backend-orch-feature とその prompt に限定する
- API 契約変更が疑われる場合は workflow 内で contract 整合ステージを通し、必要なら contract subproject 側の workflow へ接続する
- 長期参照する申し送りは features/_templates/ のひな形を複製して docs/features/<feature-slug>/ 配下に保存する
- local-dev と e2e の起動差分は operations/local-development-runtime.md を正本とする

## 運用ルール

- GitHub Issue の正本は GitHub 側に置く
- Issue 起票は .github/ISSUE_TEMPLATE/ のテンプレートから行う
- docs/ には Issue から参照される長期保存向けドキュメントを置く
- 一時メモ、壁打ち、未整理の調査ログは .codex/ に置く
- docs/ 配下のファイル名は *-plan.md、*-spec.md、*-notes.md のように用途が分かる名前を使う

## 補足

- API 契約の正本は contract/api-schema.yaml
- stable/ 直下の docs は集約のため backend/docs へ移設済み
- scripts/ の運用手順は operations/deploy-and-release.md を参照
