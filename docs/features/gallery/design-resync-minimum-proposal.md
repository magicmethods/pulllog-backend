# Gallery Design Resync Minimum Proposal

## Purpose

- gallery 初期表示失敗を、個別の timeout 調整ではなく API 設計・契約・frontend/backend の責務分離で解消する
- frontend と backend の実装判断を再同期し、再発しにくい最小構成へ整理する
- backend/stable を起点に、contract と frontend への handoff packet を作れる粒度まで仕様を固定する

## Fixed Decisions

- `x-api-key` は維持し、contract に明示する
- gallery 初期表示の SLO は以下で定義する
  - initial target: 3 seconds
  - hard timeout: 8 seconds
- gallery 初期表示は `GET /gallery/bootstrap` を使う bootstrap API 方式へ変更する

## Request Summary

- 現行の gallery 初期表示は `GET /gallery/assets` と `GET /gallery/usage` の 2 本呼びで構成されている
- frontend E2E では `data-e2e-gallery-state` が `loading -> error` になり、`API Request timed out` に落ちている
- upload 系の個別論点とは別に、初期表示の設計責務を再同期する必要がある

## Current Drift

### 1. Authentication Contract Drift

- contract は gallery protected endpoints を `CsrfAuth` のみで表現している
- stable runtime は実際には `x-api-key` と `x-csrf-token` の両方を要求している
- このため、contract と runtime の認証要件が一致していない

Evidence:

- [contract/schemas/security.yaml](contract/schemas/security.yaml#L7)
- [contract/paths/gallery.yaml](contract/paths/gallery.yaml#L1)
- [backend/stable/bootstrap/app.php](backend/stable/bootstrap/app.php#L15)

### 2. Initial Render Responsibility Drift

- frontend は初期表示で list と usage を独立 fetch し、どちらか一方でも reject すると画面全体を `error` 扱いにしている
- これは「部分成功を許容しない UI 状態設計」であり、初期表示責務が API 集約ではなく client orchestration に寄っている

Evidence:

- [frontend/pages/gallery.vue](frontend/pages/gallery.vue#L67)

### 3. Timeout Budget Drift

- frontend API client は generic timeout を 10 秒で abort する
- 一方で E2E の page wait は 45 秒なので、実際には UI 待機より前に API client が失敗している
- SLO が contract / backend / frontend のどこにも明文化されていない

Evidence:

- [frontend/composables/useAPI.ts](frontend/composables/useAPI.ts#L48)
- [frontend/tests/e2e/pages/GalleryPage.ts](frontend/tests/e2e/pages/GalleryPage.ts#L523)

### 4. Status and Error Drift

- contract では detail の権限不足を 404 扱いだが、controller は 403 を返す
- 419 CSRF mismatch も contract では十分に分離されていない

Evidence:

- [contract/paths/gallery.yaml](contract/paths/gallery.yaml#L124)
- [backend/stable/app/Http/Controllers/Gallery/GalleryAssetController.php](backend/stable/app/Http/Controllers/Gallery/GalleryAssetController.php#L95)

## Proposed Target Architecture

### 1. Bootstrap API

- 新規 endpoint: `GET /gallery/bootstrap`
- 目的: 初期表示に必要な `usage + first page assets` を 1 回の request で返す
- bootstrap は gallery landing 専用とし、詳細な pagination / filter 継続取得は既存 `GET /gallery/assets` を使う

Proposed response shape:

```json
{
  "data": {
    "assets": [],
    "usage": {
      "usedBytes": 0,
      "maxBytes": 0,
      "remainingBytes": 0,
      "filesCount": 0
    }
  },
  "links": {
    "first": null,
    "last": null,
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": null,
    "last_page": 1,
    "path": "",
    "per_page": 10,
    "to": null,
    "total": 0,
    "links": []
  }
}
```

Notes:

- query は現行 `GET /gallery/assets` の `page`, `per`, `from`, `to`, `log_id`, `tags`, `q` を引き継ぐ
- 初期表示の default `per` は 10 を維持してよい

### 2. Endpoint Role Split

- `GET /gallery/bootstrap`
  - 初期表示専用
  - SLO 管理対象
- `GET /gallery/assets`
  - pagination / filter / load more 継続取得
  - 初期表示の primary entrypoint からは外す
- `GET /gallery/usage`
  - mutation 後 refresh 用として残す
  - 初期表示の primary entrypoint からは外す

### 3. Timeout and SLO Model

- target: 3 seconds
  - backend + frontend proxy + browser render を含む初期表示成功目標
- hard timeout: 8 seconds
  - bootstrap endpoint は server 側でも timeout budget を持ち、超過時は 504 を返す方針を推奨
- frontend では bootstrap call の timeout を 8 秒に合わせ、generic 10 秒とは別の per-call override を使う

### 4. UI State Model

- `ready` は bootstrap 成功のみで決定する
- `error` は bootstrap 全体失敗時のみ
- 既存の list / usage の片側失敗に引きずられる状態遷移をやめる

## Contract Change Proposal

### Security

- `contract/schemas/security.yaml` に `ApiKeyAuth` を追加する

Proposed scheme:

```yaml
ApiKeyAuth:
  type: apiKey
  in: header
  name: x-api-key
  description: API access key required for protected Pulllog endpoints
```

- gallery protected operations の security は AND 条件で以下に統一する

```yaml
security:
  - ApiKeyAuth: []
    CsrfAuth: []
```

Target operations:

- `POST /gallery/assets/upload-ticket`
- `GET /gallery/assets`
- `POST /gallery/assets`
- `GET /gallery/assets/{assetId}`
- `PATCH /gallery/assets/{assetId}`
- `DELETE /gallery/assets/{assetId}`
- `GET /gallery/usage`
- 新規 `GET /gallery/bootstrap`

### New Path

- `contract/paths/gallery.yaml` に `GET /gallery/bootstrap` を追加する
- response schema は新規 `GalleryBootstrapResponse`

### Error Shape Alignment

- 401: `x-api-key` 欠落・不正
- 419: `x-csrf-token` 欠落・不正・失効
- 403: 権限不足
- 504: bootstrap hard timeout 超過

### Existing Drift to Resolve

- `GET /gallery/assets/{assetId}` の forbidden を 404 ではなく 403 で contract に揃える

## Backend Implementation Outline

### New Backend Surface

- route: `GET /api/v1/gallery/bootstrap`
- controller candidate: `GalleryBootstrapController` もしくは `GalleryAssetController@bootstrap`

Recommended minimum:

- 新規 controller を作るより、責務分離が明確なら `GalleryBootstrapController` を追加する
- 内部では list と usage の query をまとめて実行し、レスポンス整形は bootstrap resource で一元化する

### Backend SLO Enforcement

- bootstrap controller で処理時間計測を標準化する
- 8 秒超過を server timeout / gateway timeout で扱えるようにする
- assets, usage 個別の slow log は当面残し、bootstrap 導入後に削除可否を判断する

### Existing Endpoints

- `GET /gallery/assets` は継続
- `GET /gallery/usage` は継続
- 初期表示向け primary endpoint としては非推奨扱いに切り替える

## Frontend Implementation Outline

### Initial Load Flow

- `pages/gallery.vue` の `Promise.allSettled([fetchList, fetchUsage])` を廃止
- `useGalleryStore.fetchBootstrap()` を追加し、初期表示は bootstrap のみ呼ぶ

### Type and Normalizer

- `GalleryBootstrapResponse` 型追加
- bootstrap 用 normalizer 追加
- list / usage の既存 normalizer は継続利用

### Timeout Policy

- gallery bootstrap call は timeout 8 秒 override
- generic API client の default 10 秒は維持してよいが、gallery bootstrap では明示上書きする

### E2E

- `data-e2e-gallery-state` は bootstrap 成功時のみ `ready`
- gallery runtime smoke / gallery upload direct は bootstrap call を待つ形へ更新

## Migration Strategy

### Phase 1: Contract First

- `ApiKeyAuth` を contract に追加
- gallery protected endpoints の security を更新
- `GET /gallery/bootstrap` と `GalleryBootstrapResponse` を追加
- detail forbidden の 403 を contract に揃える

### Phase 2: Backend Additive Implementation

- `GET /gallery/bootstrap` を追加
- 既存 `GET /gallery/assets` と `GET /gallery/usage` は維持
- bootstrap に処理時間ログと 8 秒 hard timeout を実装

### Phase 3: Frontend Switch

- 初期表示を bootstrap に切り替え
- load more / filter / detail / update / delete は既存 routes 継続
- upload flow は既存 ticket + direct upload を継続

### Phase 4: Deprecation Notice

- `GET /gallery/usage` は初期表示用途として soft deprecate
- `GET /gallery/assets` は pagination endpoint として継続利用するため hard deprecate しない

## Why This Is Smarter Than Continuing Local Fixes

- timeout の根本責務を client orchestration から API 集約へ戻せる
- contract と runtime の認証 drift を解消できる
- frontend/backend が同じ SLO 予算で設計できる
- E2E が 2 本の API のレース条件ではなく、1 本の bootstrap 成否に基づいて判定できる
- 既存 endpoint を壊さない additive migration なので移行リスクが小さい

## Cross-Team Handoff Packet

### Contract team

- 必要変更:
  - `ApiKeyAuth` 追加
  - gallery protected operations の security 更新
  - `GET /gallery/bootstrap` と `GalleryBootstrapResponse` 追加
  - detail forbidden の 403 反映
- 検証期待値:
  - `npm run validate`
  - gallery path と schema bundle の確認
- rollback 観点:
  - additive change のため rollback は新規 path と security 定義の差し戻しで対応可

### Frontend team

- 必要変更:
  - gallery 初期表示を bootstrap API 化
  - bootstrap 用 normalizer / store action / timeout override 追加
  - E2E を bootstrap 待機へ更新
- 検証期待値:
  - `gallery-runtime-smoke`
  - `gallery-upload-direct`
  - load more / detail / save / delete の回帰確認
- rollback 観点:
  - 初期表示ロジックを旧 `fetchList + fetchUsage` に戻せるよう分離実装にする

## Open Questions

1. bootstrap hard timeout 8 秒を contract 上で 504 として明示するか、実装規約に留めるか
2. bootstrap response の `data` を `assets`, `usage`, `prefetchHints` のような拡張可能 shape にするか
3. upload 完了後の refresh は bootstrap 再取得に寄せるか、既存 `list + usage` の partial refresh を維持するか

## Recommended Next Action

1. この提案書を backend の設計正本として承認する
2. contract 側 handoff として `ApiKeyAuth + GET /gallery/bootstrap` の変更指示を出す
3. backend 側では bootstrap endpoint の architecture note を追加し、実装段へ進める
4. frontend 側は bootstrap 移行前提で current gallery page の依存箇所を洗い出す