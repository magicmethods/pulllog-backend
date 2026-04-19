# ローカル開発ランタイム運用

この文書は、backend/stable をローカル開発で起動する際の運用レーンを整理するための正本です。

## 目的

- 通常の画面開発と Playwright E2E を別レーンとして扱う
- local-dev と e2e の env、起動コマンド、DB 取り扱い差分を明示する
- API_KEY や API_BASE_URI の混線による 401 / 疎通不良を防ぐ

## 運用レーン

| レーン | 主用途 | frontend 側 | backend 側 | DB の扱い |
|---|---|---|---|---|
| local-dev | 通常の画面開発、手動ブラウザ確認、疎通確認 | `.env.local` | 通常 `.env` | 既存のローカル開発 DB を継続利用 |
| e2e | Playwright による再現性重視の E2E 実行 | `.env.e2e` | `.env.e2e` | 必要時のみ E2E 用 DB を再初期化 |

## local-dev レーン

### 使う場面

- frontend の通常開発
- 手動ブラウザ確認
- 開発中のデータを残したまま検証したい場合

### frontend 側前提

- `pnpm dev` を使う
- `.env.local` を利用する

### backend 側前提

- 通常の `.env` を使う
- 起動コマンドは `php artisan serve --host=127.0.0.1 --port=3030` を基本とする

### 注意点

- frontend の `SECRET_API_KEY` と backend の `API_KEY` は同じ値に揃える
- `API_BASE_URI` は frontend 側の通常 local 前提に合わせて `/api/v1` を基準にする
- local-dev で backend を `.env.e2e` 起動に切り替えない

## e2e レーン

### 使う場面

- Playwright による標準マトリクス実行
- seed 済みの再現性ある状態で E2E を確認したい場合
- 手動検証でも、E2E 専用データセットを使って切り分けたい場合

### frontend 側前提

- `pnpm run test:e2e` / `pnpm run test:e2e:case` / `pnpm run test:e2e:tag` を使う
- `.env.e2e` を利用する

### backend 側前提

- `.env.e2e` を使う
- Playwright からは `composer run e2e:serve` が自動起動される

### `e2e:prepare` と `e2e:serve` の差分

- `composer run e2e:prepare`
  - `.env.e2e` の作成または補完
  - `APP_KEY` の準備
  - `storage:link`
  - `migrate:fresh --seed --env=e2e --force`
  - つまり E2E 用 DB を再初期化する
- `composer run e2e:serve`
  - `php artisan serve --env=e2e --host=127.0.0.1 --port=3030`
  - つまり `.env.e2e` で起動するだけで、DB 初期化は行わない

### 注意点

- Playwright 実行そのものでは毎回 DB 初期化はされない
- seed 済みクリーン状態が必要なときだけ `e2e:prepare` を明示的に実行する
- E2E 用 DB は local-dev とは分離して扱う

## 混線禁止事項

- frontend の `.env.local` と backend の `.env.e2e` を組み合わせない
- frontend の `.env.e2e` と backend の通常 `.env` を組み合わせない
- 通常開発で `e2e:serve` を常用しない

## 混線時に起こりやすい症状

- `API_KEY` 不一致による login 401
- `APP_FRONTEND_URL` や `CORS_ALLOWED_ORIGINS` の不一致によるブラウザ疎通不良
- `API_BASE_URI` 前提の差による疎通先ミスマッチ

## 推奨手順

### 通常開発

1. backend/stable で通常 `.env` を使って `php artisan serve --host=127.0.0.1 --port=3030` を起動する
2. frontend で `pnpm dev` を起動する
3. login や gallery 開発の手動確認はこの組み合わせで行う

### Playwright E2E

1. クリーンな E2E DB が必要なら `pnpm run test:e2e:prepare` を実行する
2. `pnpm run test:e2e` もしくは `pnpm run test:e2e:case -- <case-id>` を実行する
3. Playwright が backend の `e2e:serve` を自動起動する

## 関連文書

- `../../README.md`
- `../features/stable-runtime-e2e-alignment/workflow-notes.md`
- `../features/stable-runtime-e2e-alignment/local-runtime-e2e-alignment-plan.md`
- `../../stable/.env.example`
- `../../stable/.env.e2e.example`