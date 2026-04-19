# Stable Runtime E2E Alignment Workflow Notes

## Feature

- 名前: backend/stable ローカル起動と E2E 起動の運用整理
- feature-slug: stable-runtime-e2e-alignment
- 参照 Issue / 要件: frontend 側 gallery 開発を阻害している backend/stable のローカル起動混線の調査とベストプラクティス整理

## Request Summary

- frontend 側の gallery 開発で、backend/stable の通常ローカル起動と E2E 起動の使い分けが不明確なため疎通不良が発生している。
- backend/stable には通常ローカル運用と E2E 用運用の少なくとも 2 系統があり、運用ケース、起動手順、環境差を整理する必要がある。
- もし通常ローカル起動側に不足や古い前提があれば、運用資料または環境例の再整備対象として切り出す。

## Scope and Non-goals

- 対象: backend/stable のローカル運用レーン整理、環境差の可視化、ドキュメント整備候補の抽出
- 対象: frontend から backend/stable へ疎通する通常開発フローと Playwright E2E フローの境界整理
- 非対象: beta の起動方式、beta の API 運用、beta の調査
- 非対象: API request/response shape の変更
- 非対象: gallery direct upload の実装修正

## Required Stages

- Architect: 完了
- Contract alignment: 現時点では不要
- Implementer: 未着手
- Reviewer: 未着手

## Current Status

- 現在ステージ: 調査整理完了、文書化
- 状態: backend/stable の運用レーンを 2 本に分けて扱う方針を確定
- local-dev レーンは frontend の .env.local と backend の通常 env を組み合わせる前提とする
- e2e レーンは frontend の .env.e2e と backend の .env.e2e を組み合わせる前提とする
- frontend の通常ローカル起動系は backend を通常 env で 127.0.0.1:3030 に起動する前提を確認した
- Playwright E2E は backend の composer run e2e:serve と frontend の .env.e2e を使う前提を確認した
- `e2e:prepare` は E2E 用 DB 初期化を含み、`e2e:serve` は起動のみであることを確認した
- 両レーンを混在させると API_KEY 不一致により login 401 が起こりうることを確認した
- gallery direct upload は frontend 実装上 `x-api-key` を付与しておらず、backend の `POST /gallery/assets` が `auth.apikey` 配下にあるため、別ブロッカーとして 401 になりうることを確認した

## Blockers and Open Questions

- backend/stable/.env.example の APP_FRONTEND_URL と CORS_ALLOWED_ORIGINS の既定値が、現行の frontend ローカル運用とずれている可能性がある
- backend/stable/.env.example と backend/stable/.env.e2e.example で API_BASE_URI の慣例差があり、少なくとも明文化が必要
- local-dev と e2e の URL 規約やポート分離をさらに強めるかは、実装段階で判断が必要

## Decision Log

- 日付: 2026-04-19
  - 判断: backend/stable のローカル運用は local-dev と e2e の 2 レーンで明示的に扱う
  - 理由: frontend 側の通常開発フローと Playwright E2E フローで参照する env と起動コマンドがすでに分かれており、混線が gallery 開発の阻害要因になっているため
  - 次アクション: backend 側の運用ドキュメントに 2 レーンの目的、起動手順、混線禁止を明記する

- 日付: 2026-04-19
  - 判断: 現段階では contract 変更ではなく運用整理を優先する
  - 理由: 主問題は request/response shape ではなく、env と起動手順の混線、および backend 側サンプル設定の追随不足であるため
  - 次アクション: backend/stable/.env.example と backend/stable/.env.e2e.example の説明責務を見直す

- 日付: 2026-04-19
  - 判断: gallery direct upload は local runtime 混線とは別の認証ヘッダ課題として切り分ける
  - 理由: frontend の direct upload 実装は `x-csrf-token` と `x-upload-token` は付与するが `x-api-key` は付与しておらず、backend の `POST /gallery/assets` は `auth.apikey` 配下のため 401 要因になりうるため
  - 次アクション: frontend 側では direct upload 経路で `x-api-key` を確実に渡す設計に改めるか、Nitro proxy 経由に統一する方針を検討する

## Next Action

- backend/stable の通常ローカル運用と E2E 運用を整理した運用ドキュメントを追加する
- backend/stable/.env.example の APP_FRONTEND_URL と CORS_ALLOWED_ORIGINS の既定値を見直す
- backend/stable/.env.example と backend/stable/.env.e2e.example の API_BASE_URI 差分を統一または明文化する
- frontend 側へ再確認対象として、通常開発は local-dev レーン固定、Playwright は e2e レーン固定であることを申し送る
- gallery direct upload の `x-api-key` 欠落を別件ブロッカーとして frontend / backend 間で共有する