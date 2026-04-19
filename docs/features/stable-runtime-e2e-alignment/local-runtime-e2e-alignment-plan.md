# Local Runtime E2E Alignment Plan

## Overview

- feature 名: backend/stable ローカル起動と E2E 起動の運用整理
- 対象 Issue / 要件: frontend 側 gallery 開発を阻害している backend/stable のローカル環境混線の調査と再整備方針整理
- 作成日: 2026-04-19

## Requirement Summary

- backend/stable に存在する通常ローカル起動と E2E 起動の運用差を明確化する
- frontend 側が通常開発時に使う backend 起動方式と、Playwright E2E 実行時に使う backend 起動方式を混在させない運用を確立する
- 運用差を backend 側ドキュメントと環境サンプルへ反映できる状態に整理する
- 通常ローカル起動に不足や古い前提がある場合は、それを再整備対象として切り出す

## Non-goals

- beta の起動整理や beta の運用見直し
- API contract の request/response shape 変更
- gallery direct upload の実装修正
- 認証方式そのものの redesign

## Proposed Architecture

- ローカル運用は 2 本の明示的なレーンとして扱う
- local-dev レーン:
  - frontend は .env.local を使う
  - backend は通常 env を使う
  - 手動ブラウザ確認、画面開発、通常のローカル疎通確認はこのレーンを使う
- e2e レーン:
  - frontend は .env.e2e を使う
  - backend は .env.e2e を使う
  - Playwright による自動 E2E はこのレーンを使う
- 運用ドキュメントでは、local-dev と e2e の env ファイル、起動コマンド、想定利用者、想定用途を対比で示す
- backend 側のサンプル env は、frontend 実運用に追随しているかを継続確認できるようにする

## Impacted Files

- backend/stable/composer.json
- backend/stable/.env.example
- backend/stable/.env.e2e.example
- backend/docs/operations/service-operations-overview.md
- backend/docs/features/stable-runtime-e2e-alignment/workflow-notes.md
- frontend/.vscode/tasks.json
- frontend/tests/playwright/playwright.config.ts
- frontend/.env.local
- frontend/.env.e2e

## API and Contract Alignment

- 対象 endpoint: 調査対象は特定 endpoint ではなく runtime lane 全体
- ../contract/api-schema.yaml との整合: 現時点の主問題は API 契約差分ではなく運用差分である
- contract 更新要否: 現時点では不要
- frontend 影響: api/endpoints.ts の変更よりも、どの env と backend 起動方式を組み合わせるべきかの運用明記が主対象
- drift リスク: API_BASE_URI の慣例差が local-dev と e2e で残っているため、実装より前に説明責務を整理しないと再発しうる

## Auth and Data Impact

- auth / authorization: 両レーンを混在させると API_KEY 不一致により login 401 が起こりうる
- middleware: backend 側の auth.apikey 前提を崩す話ではなく、どの env でどの key を使うかを運用上明示する話である
- DB / migration: e2e レーンは専用 DB を前提に初期化手順を持つが、local-dev レーンは通常開発 DB 前提である
- backward compatibility: API 互換性というより、開発運用互換性の問題。既存運用の暗黙知を明文化しないと今後も混線する

## Verification Strategy

- backend 側起動手順と frontend 側起動手順の対応表を作り、local-dev と e2e が交差しないことを確認する
- local-dev レーンでは frontend 通常起動と backend 通常起動の組み合わせで login から gallery 開発が再開できるかを確認対象にする
- e2e レーンでは Playwright 設定どおり backend の composer run e2e:serve と frontend の .env.e2e が使われていることを確認する
- .env.example と .env.e2e.example の APP_FRONTEND_URL、CORS_ALLOWED_ORIGINS、API_BASE_URI の説明が現行運用と一致するかを確認する
- gallery direct upload 系は別途 x-api-key 付与有無を確認し、runtime 整理と切り分けて扱う

## Implementation Order

1. backend 側 docs に local-dev と e2e の用途、起動コマンド、混線禁止事項を明記する
2. backend/stable/.env.example と backend/stable/.env.e2e.example の既定値と説明を見直す
3. API_BASE_URI の差分を維持するなら理由を文書化し、不要なら統一案を検討する
4. frontend 側へ再確認対象を申し送りし、gallery 開発を local-dev レーンで再開できるかを確認する

## Acceptance Criteria

- backend/stable の通常ローカル起動と E2E 起動が local-dev と e2e の 2 レーンとして説明されている
- frontend 側が通常開発時に backend 通常 env を使うべきことが backend docs から判断できる
- Playwright E2E が backend の e2e:serve と frontend の .env.e2e を使う前提が backend docs でも明記されている
- API_KEY 不一致による login 401 が混線起因であることを、運用説明として追跡できる
- backend/stable/.env.example の APP_FRONTEND_URL と CORS_ALLOWED_ORIGINS の既定値見直し要否が明文化されている
- API_BASE_URI の慣例差が放置ではなく、意図または修正方針として説明されている

## Risks and Open Questions

- backend/stable/.env.example の値を直すだけでは、frontend 側既存の暗黙運用が残ると再発する可能性がある
- API_BASE_URI を統一する場合は、既存の local-dev と e2e 手順への影響確認が必要
- gallery direct upload の x-api-key 付与有無が未確認であり、local runtime 整理後も別の疎通課題として残る可能性がある
- 将来的に local-dev と e2e のポートや URL をさらに分離した方が誤運用を防げる可能性がある