# Issue: ギャラリー公開画像向け短縮URL実装

## 背景
- 現状、ギャラリー資産は `GALLERY_DISK=public`（`storage/app/public`）に保存されており、`/storage/...` を経由した直接アクセスが可能。
- 公開可否を `visibility` で制御する設計だが、`public` 判定の画像もサイン付きURLを経由せず取得できてしまい、意図しない公開につながる。
- 実装中のアップロードチケットは private ディスクを前提にしても問題なく動作するため、非公開領域に統一しつつ、公開用に短縮URLを発行する仕組みが求められる。

## 要件
1. `GALLERY_DISK` を `private` に切り替え、ギャラリー資産ファイルはすべて非公開ディスクで管理する。
2. `visibility === "public"` の資産は、短縮コード経由で `https://img.pulllog.net/{code}` からアクセスできるようにする。
3. 短縮URLは原則永続的に有効だが、資産が削除されたり `visibility` が `private` / `unlisted` に戻った場合は無効化されること。
4. CDN やブラウザキャッシュを想定したレスポンスヘッダ（`Cache-Control`, `ETag` 等）を付与し、帯域最適化を図る。
5. 既存 API (`GET /api/v1/gallery/assets`, `/gallery/assets/{id}` 等) は `appId` / `appKey` を保持したまま、短縮URL情報を返せるよう拡張する。

## 詳細設計
### データベース
- 新テーブル `gallery_asset_links`
  - `id` (bigint)
  - `asset_id` (uuid, FK -> `gallery_assets.id` ON DELETE CASCADE)
  - `code` (varchar 32, unique)
  - `expire_at` (timestamp, nullable)
  - `last_accessed_at` (timestamp, nullable)
  - `hit_count` (bigint default 0)
  - `created_at` / `updated_at`
  - `UNIQUE(asset_id)` および `UNIQUE(code)`

### 保存ディスク
- `.env` を `GALLERY_DISK=private` に変更。
- `config/gallery.php` の既定値も `private` に統一。
- `GalleryStorage` 等はそのまま `Storage::disk(config('gallery.disk'))` を利用するため変更不要。

### 短縮URLの発行・管理
- 資産作成または `visibility` が `public` に変更されたタイミングで `gallery_asset_links` を upsert。
- `visibility` が `public` 以外へ変わった場合はリンクを削除。
- サービスクラス `GalleryAssetLinkService` を新設し、`createOrRefreshLink(GalleryAsset $asset)` と `deleteLink(GalleryAsset $asset)` を提供。
- コード生成は `Str::lower(Str::random(10))` 等で重複検出を行いながら実施。

### ルーティング / 配信
- `routes/img.php` を作成し、`Route::domain('img.pulllog.net')->group(...)` 内で `Route::get('{code}', [GalleryAssetPublicController::class, 'show'])` を定義。
- コントローラでは `gallery_asset_links.code` を参照し、対応資産が `visibility='public'` か検証。
- 問題なければ `Storage::disk('private')->response($asset->path)` でファイルを返し、`Cache-Control: public, max-age=604800` や `ETag` を付与。
- レスポンス後に `hit_count` と `last_accessed_at` を更新。

### API レスポンス拡張
- `GalleryAsset` モデルに `link()` リレーションを追加。
- `GalleryAssetResource` に `publicUrl` プロパティを追加（リンクが存在するときのみ `https://img.pulllog.net/{code}` を返す）。
- `index` / `show` で `->with('link:asset_id,code')` を行い JSON に短縮URLを含める。

### 可視性変更フロー
- `GalleryAssetController@store` / `@update` で `visibility` の変更を検知し、`GalleryAssetLinkService` を呼び出す。
- `visibility='public'` でアップロードされた場合は即座にリンク作成。`private` / `unlisted` に戻すとリンク削除。

### CDN / キャッシュ戦略
- `img.pulllog.net` を Cloudflare 等の CDN に向ける。
- 画像更新時はコード再発行または CDN パージで整合性を確保。
- HTTPS は CDN もしくはロードバランサで終端。

## 実装ステップ案
1. `.env` を `GALLERY_DISK=private` に変更し、`config:cache` を再生成。
2. マイグレーション `create_gallery_asset_links_table` を追加し適用。
3. モデル `GalleryAssetLink` とリレーション、`GalleryAssetLinkService` を実装。
4. `GalleryAssetController` / `GalleryAssetResource` を拡張し、公私切り替え・レスポンスを対応。
5. 新コントローラ `GalleryAssetPublicController` と `routes/img.php` を追加、`RouteServiceProvider` に読み込みを設定。
6. テスト（public → 短縮URL取得 → `img.pulllog.net/{code}` で 200、visibility 切替でリンク無効化）を整備。
7. 既存 `visibility='public'` データ用に、バッチ等でリンク生成を行う。

## ローンチ手順
1. `.env` 変更 (`GALLERY_DISK=private`) と `php artisan config:cache`。
2. `php artisan migrate` で新テーブルを反映。
3. 既存 public 資産に対して短縮リンク生成バッチを実行。
4. DNS で `img.pulllog.net` をバックエンド/CDN に向け、TLS 証明書を適用。
5. `img.pulllog.net/{code}` の疎通確認（curl で 200）と CDN キャッシュ設定。
6. フロントエンドが `publicUrl` を利用して画像を表示するよう調整。
7. 旧 `/storage` 直アクセスを無効化（`storage:link` の削除、もしくは Web サーバ設定でブロック）。

## 備考
- 旧 URL (`/storage/...`) を利用している箇所があれば、リダイレクトや説明を用意する。
- 将来的にリンクの有効期限・アクセス制限を追加する余地を残しておく。
- CDN キャッシュ更新のために、コード再発行やバージョニングポリシーを決めておく。
