### 手動テスト結果
1. 実ディスク書き込みと公開URL確認
   - `php artisan storage:link` を実行し、`storage/app/public` → `public/storage` のシンボリックリンクを張る。
      - 検証結果: Ok
   - `curl -X POST http://localhost:8000/api/v1/gallery/assets` に `x-api-key`・`x-csrf-token` ヘッダとログイン済みセッションCookieを付与し、実ファイル（例: `tests/Fixtures/gallery/sample-landscape.jpg`）をアップロードする。
      - 前提条件: Laravel側にViewはなく、フロントエンドはCORS環境なのでセッションCookieは使用していない。`POST /api/v1/auth/login` の戻り値からCSRFトークンを `x-csrf-token` ヘッダに付与してリクエストすることで認証ユーザーとなる。
      - 検証結果: Ok
   - `storage/app/public/gallery/` 配下に原本・`thumb_small`・`thumb_large` が生成されていることを確認し、`http://localhost:8000/storage/...` で 200 応答になるかをブラウザで検証する。
      - 検証結果: Ok。原本が `{FQDN}/storage/gallery/YYYY/MM/img_*****.*****.jpg` に、サムネイルが `{FQDN}/storage/gallery/YYYY/MM/thumbs/s_*****.*****.jpg` （小）と `{FQDN}/storage/gallery/YYYY/MM/thumbs/l_*****.*****.jpg` （大）で生成されていた。
2. サムネイル生成と使用量集計の整合性確認
   - 複数サイズ（1MB超・300KB程度）の画像を連続アップロードし、`gallery_assets` テーブルの `width`・`height`・`bytes_thumb_small` などが実サイズで埋まることを `php artisan tinker` もしくは `SELECT` で確認する。
      - 検証結果: NG。まず 1. のテスト後、`gallery_assets` テーブルにアップロードされた画像データが登録されていない。テーブルはマイグレーション成功後なので作成済み。
      - 原因: PostgreSQL で `storage_disk` ENUM に `public` が未定義のまま `public` ディスクに保存しようとしたため INSERT が失敗しトランザクションがロールバックしていた。
      - 対応: `database/migrations/2025_10_06_170000_add_public_to_storage_disk_enum.php` で ENUM に `public` を追加し、`app/Http/Controllers/Gallery/GalleryAssetController.php` で永続化失敗時に保存済みファイルをクリーンアップするよう修正。`php artisan test --filter=GalleryAssetTest` を再実行し正常終了を確認。
         - 再検証結果: Ok
   - `php artisan gallery:recalculate-usage --user_id=<ID>` 実行後、`gallery_usage_stats` の `bytes_used`・`files_count` が実ファイルと一致し、`GET /api/v1/gallery/usage` が一致した値を返すことを確認する。
      - 検証結果: NG。同上のように、画像アップロード後、 `gallery_usage_stats` テーブルにレコードがない。テーブルはマイグレーション成功後なので作成済み。
      - 原因: 上記の INSERT ロールバックにより更新トリガーが発火せず、使用量集計が作成されていなかった。
      - 対応: 上記修正によりギャラリー資産の登録が正常化し、トリガー経由で `gallery_usage_stats` が更新されることを確認。
         - 再検証結果: Ok
3. 認証ヘッダとセッション維持の確認
   - `POST /api/v1/auth/login` → `GET /api/v1/session/csrf`（既存エンドポイント）で取得したトークンを用い、ギャラリーAPIへアクセスする。
      - 前提条件: Laravel側にViewはなく、フロントエンドはCORS環境なのでセッションCookieは使用していない。`POST /api/v1/auth/login` の戻り値からCSRFトークンを `x-csrf-token` ヘッダに付与してリクエストすることで認証ユーザーでのアクセスとなる。ギャラリーAPIへのアクセスは `curl -X GET -H "x-api-key:*****" -H "x-csrf-token:*****" -H "Content-Type: application/json" http://localhost:3030/api/v1/gallery/assets` で実施した。
         - 再検証結果: Ok。レスポンス結果は下記の通り:
         ```json
         {
            "data":[
                {
                    "id":"5f6c4a0e-a8d7-43e7-b17f-1db22057e05f",
                    "userId":3,
                    "logId":null,
                    "disk":"public",
                    "path":"gallery\/2025\/10\/img_68e372ab8824a8.32826279.png",
                    "url":"https:\/\/pull.log:4649\/storage\/gallery\/2025\/10\/img_68e372ab8824a8.32826279.png",
                    "thumbSmall":"gallery\/2025\/10\/thumbs\/s_68e372abb62c88.14098441.png",
                    "thumbSmallUrl":"https:\/\/pull.log:4649\/storage\/gallery\/2025\/10\/thumbs\/s_68e372abb62c88.14098441.png",
                    "thumbLarge":"gallery\/2025\/10\/thumbs\/l_68e372abeff875.33450171.png",
                    "thumbLargeUrl":"https:\/\/pull.log:4649\/storage\/gallery\/2025\/10\/thumbs\/l_68e372abeff875.33450171.png",
                    "mime":"image\/png",
                    "bytes":1438002,
                    "bytesThumbSmall":18907,
                    "bytesThumbLarge":182858,
                    "width":1024,
                    "height":1024,
                    "hashSha256":"7c48ac1638c8dfc8120b35b02ccfa847867026caecc04d0a75b07c69e8e956a6",
                    "title":null,
                    "description":null,
                    "tags":[],
                    "visibility":"private",
                    "createdAt":"2025-10-06T07:41:32+09:00",
                    "updatedAt":"2025-10-06T07:41:32+09:00",
                    "deletedAt":null
                },
                {
                    "id":"192e88c0-165a-4809-b710-dc16132386ac",
                    "userId":3,
                    "logId":null,
                    "disk":"public",
                    "path":"gallery\/2025\/10\/img_68e37275eb9761.26070555.jpg",
                    "url":"https:\/\/pull.log:4649\/storage\/gallery\/2025\/10\/img_68e37275eb9761.26070555.jpg",
                    "thumbSmall":"gallery\/2025\/10\/thumbs\/s_68e37276168a88.01761094.jpg",
                    "thumbSmallUrl":"https:\/\/pull.log:4649\/storage\/gallery\/2025\/10\/thumbs\/s_68e37276168a88.01761094.jpg",
                    "thumbLarge":"gallery\/2025\/10\/thumbs\/l_68e372763450b0.40375594.jpg",
                    "thumbLargeUrl":"https:\/\/pull.log:4649\/storage\/gallery\/2025\/10\/thumbs\/l_68e372763450b0.40375594.jpg",
                    "mime":"image\/jpeg",
                    "bytes":336916,
                    "bytesThumbSmall":15780,
                    "bytesThumbLarge":175564,
                    "width":800,
                    "height":600,
                    "hashSha256":"ea9dc83d0f012a8912a19e49274b616ae3e4db3cfb60754b773cc855c4207c76",
                    "title":null,
                    "description":null,
                    "tags":[],
                    "visibility":"private",
                    "createdAt":"2025-10-06T07:40:38+09:00",
                    "updatedAt":"2025-10-06T07:40:38+09:00",
                    "deletedAt":null
                },
                {
                    "id":"5a261c16-1ad0-43bb-9133-0b85b6265865",
                    "userId":3,
                    "logId":null,
                    "disk":"public",
                    "path":"gallery\/2025\/10\/img_68e370e0aac3e8.80393221.jpg",
                    "url":"https:\/\/pull.log:4649\/storage\/gallery\/2025\/10\/img_68e370e0aac3e8.80393221.jpg",
                    "thumbSmall":"gallery\/2025\/10\/thumbs\/s_68e370e0d18295.21670042.jpg",
                    "thumbSmallUrl":"https:\/\/pull.log:4649\/storage\/gallery\/2025\/10\/thumbs\/s_68e370e0d18295.21670042.jpg",
                    "thumbLarge":"gallery\/2025\/10\/thumbs\/l_68e370e1115234.44300768.jpg",
                    "thumbLargeUrl":"https:\/\/pull.log:4649\/storage\/gallery\/2025\/10\/thumbs\/l_68e370e1115234.44300768.jpg",
                    "mime":"image\/jpeg",
                    "bytes":40479,
                    "bytesThumbSmall":3939,
                    "bytesThumbLarge":27000,
                    "width":1200,
                    "height":640,
                    "hashSha256":"8d5767ee7ac55d46926f41a886e29f135481eee71f55409755b4c670682af709",
                    "title":null,
                    "description":null,
                    "tags":[],
                    "visibility":"private",
                    "createdAt":"2025-10-06T07:33:53+09:00",
                    "updatedAt":"2025-10-06T07:33:53+09:00",
                    "deletedAt":null
                }
            ],
            "links":{
                "first":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets?page=1",
                "last":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets?page=1",
                "prev":null,
                "next":null
            },
            "meta":{
                "current_page":1,
                "from":1,
                "last_page":1,
                "links":[
                    {
                        "url":null,
                        "label":"&laquo; Previous",
                        "active":false
                    },
                    {
                        "url":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets?page=1",
                        "label":"1",
                        "active":true
                    },
                    {
                        "url":null,
                        "label":"Next &raquo;",
                        "active":false
                    }
                ],
                "path":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets",
                "per_page":30,
                "to":3,
                "total":3
            }
         }
         ```
   - `x-api-key` もしくは `x-csrf-token` を省略した場合に 401/419 が返ること、ブラウザのセッション有効期限経過後に再ログインが必要になることを確認する。
      - 検証結果: Ok
