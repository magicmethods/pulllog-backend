# Gallery Workflow Notes

## Feature

- 名前: backend/stable gallery local-dev runtime 復旧
- feature-slug: gallery
- 参照 Issue / 要件: feature/gallery-foundation 上で local-dev runtime の gallery API が壊れており、frontend gallery 実機確認を再開できない

## Request Summary

- backend/stable の local-dev runtime で gallery API を復旧する
- 対象は stable のみで、beta は考慮しない
- frontend が local-dev レーンで gallery の FE-G5 実機確認を再開できる状態まで戻す

## Scope and Non-goals

- 対象: backend/stable の gallery runtime 復旧
- 対象: local DB / migration / runtime drift の解消
- 対象: frontend への再検証範囲の明確化
- 非対象: beta の修正
- 非対象: contract/api-schema.yaml の変更
- 非対象: frontend の direct upload 実装修正
- 非対象: gallery の新機能追加

## Required Stages

- Architect: 完了
- Contract alignment: 不要
- Implementer: 完了
- Reviewer: 完了

## Current Status

- 現在ステージ: Reviewer 完了
- 状態: local-dev gallery runtime の primary blocker は PostgreSQL の storage_disk enum drift と確定
- 現行の stable 実装は GALLERY_DISK=private 前提で揃っており、API shape 変更は不要
- PostgreSQL の既存 local DB で private/public ラベル不足を補修する migration を追加済み
- local-dev では pull 後に php artisan migrate を一度実行する必要がある
- frontend は local-dev レーンで gallery list / usage / upload-ticket を優先して再検証する段階
- upload non-mock runtime blocker については、upload-ticket は既存の auth.apikey + auth.csrf + demo.guard を維持しつつ、upload 本体のみ route 境界で auth.apikey を外す方針で stable runtime を調整済み
- frontend の local-dev 再確認では gallery runtime と upload-ticket は通過し、失敗は direct upload 本体 `POST http://localhost:3030/api/v1/gallery/assets` の 401 (`{"message":"Unauthorized"}`) のみに絞れた
- 2026-04-25 の frontend 再検証（`gallery-upload-direct`）では、`#gallery-page-root` が `loading -> error` になり初期表示で失敗
- 同レポートの UI 文言は `API Request timed out` で、usage カードは描画済みのため `GET /gallery/usage` は応答し `GET /gallery/assets` 側の遅延が主因候補
- backend 側では `GalleryAssetController@index` / `GalleryUsageController@show` に slow log を追加し、local-dev 再試験で遅延点を即照合できる状態にした
- `gallery_assets` 一覧の active レコード取得を最適化するため、PostgreSQL では partial index（`deleted_at IS NULL`）を追加する migration を作成済み
- 2026-04-25 時点の再同期方針は [docs/features/gallery/design-resync-minimum-proposal.md](docs/features/gallery/design-resync-minimum-proposal.md) を正本として扱う

## Blockers and Open Questions

- 既存 local DB に新規 migration を未適用のままでは runtime は復旧しない
- gallery Feature テストは SQLite 非互換 migration の既知制約があり、今回の差分を fully automated では担保できていない
- .env.e2e.example の GALLERY_DISK は public のままで、private 前提との差分が残っている
- focused PHPUnit は今回も SQLite 初期化時の logs_with_money view 作成で停止するため、gallery upload 修正の自動検証は PostgreSQL レーンか migration 側の別対応が必要
- runtime 証跡上の 401 文言は `AuthApiKey` と一致しており、global API prepend または stale route cache 下で upload route の例外が効き切っていない可能性が残っている

## Decision Log

- 日付: 2026-04-19
  - 判断: contract review は不要
  - 理由: 障害は request/response shape ではなく local runtime の DB enum drift であり、既存 gallery routes と contract shape を変える必要がないため
  - 次アクション: implementation を local DB 補修に限定して進める

- 日付: 2026-04-19
  - 判断: GALLERY_DISK を public に戻さず、private 前提の現行設計に local DB を追従させる
  - 理由: config、docs、gallery 実装、feature docs が private 前提で揃っており、public へ戻すのは後退になるため
  - 次アクション: storage_disk enum を idempotent に補修する migration を追加する

- 日付: 2026-04-19
  - 判断: workflow handoff は backend/docs/features/gallery/workflow-notes.md に記録する
  - 理由: backend でも frontend 同様に agent-driven の申し送りを persistent に残す運用へ揃えるため
  - 次アクション: frontend 再検証結果を受けて必要なら次の blocker を追記する

- 日付: 2026-04-20
  - 判断: upload 本体の auth.apikey 緩和は route 境界のみを source of truth とし、AuthApiKey の gallery 専用 bypass は持たない
  - 理由: frontend の browser direct upload は x-csrf-token + x-upload-token + credentials include 前提で、middleware 側の重複例外を残す理由がないため
  - 次アクション: frontend の non-mock upload 実機確認と、SQLite 非互換 migration を避けた backend 検証レーンを切り分ける

- 日付: 2026-04-21
  - 判断: direct upload 401 の再現証跡を受け、`AuthApiKey` に gallery upload 本体向けの条件付き bypass を戻す
  - 理由: frontend 再確認では upload-ticket が 200 のまま direct upload 本体だけが `Unauthorized` で 401 になっており、global API prepend や route cache が残る実 runtime で route 側例外だけでは不十分と判断したため
  - 次アクション: local-dev で `POST /api/v1/gallery/assets` の non-mock upload を再確認し、ticket は 200、direct upload は 200/201 に変わることを確認する

- 日付: 2026-04-25
  - 判断: gallery 初期表示 timeout への即時対応として、assets/usage の処理時間ログ追加と assets 一覧向けインデックス追加を先行実施
  - 理由: frontend レポートでは `API Request timed out` かつ `data-e2e-gallery-state=error` が確認され、laravel.log 単体では遅延点を特定できないため
  - 次アクション: `php artisan migrate` でインデックスを反映し、frontend `gallery-upload-direct` を再実行して再現時刻・対象ユーザーと slow log を突合する

## Next Action

- backend/stable で php artisan migrate を適用して local DB の enum drift を解消する
- frontend は local-dev で GET /api/v1/gallery/assets、GET /api/v1/gallery/usage、POST /api/v1/gallery/assets/upload-ticket を再検証する
- frontend は local-dev で POST /api/v1/gallery/assets の non-mock direct upload を x-csrf-token + x-upload-token + credentials include で再検証する
- local-dev で upload が旧 middleware 構成のまま 401 になる場合は、backend 側で `php artisan config:clear` と `php artisan route:clear` を実行して cache 影響を除外する
- local-dev で upload-ticket は 200 のまま、direct upload 本体が 401 から 200/201 に変わるかを frontend の `gallery-upload-direct` case で再確認する
- backend 側の focused PHPUnit は SQLite 初期化 blocker を別途解消するか、PostgreSQL レーンで代替検証する
- backend/stable で `php artisan migrate` を実行して `2026_04_25_140000_add_gallery_assets_active_listing_index.php` を適用する
- frontend 再試験時は report に記録される再現時刻・対象ユーザー（`standard_user`）を必ず backend へ申し送りし、`gallery.assets.index.slow` / `gallery.usage.show.slow` と照合する