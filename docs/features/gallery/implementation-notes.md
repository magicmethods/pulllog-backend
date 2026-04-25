# Gallery Implementation Notes

## 2026-04-20 gallery upload non-mock runtime blocker

- `POST /api/v1/gallery/assets/upload-ticket` は従来どおり `auth.apikey + auth.csrf + demo.guard` のまま維持。
- `POST /api/v1/gallery/assets` は route 単位で `auth.apikey` のみ除外し、`auth.csrf + demo.guard` と既存の `x-upload-token` / request user 検証で成立するよう調整。
- frontend の local-dev Playwright 再確認では upload-ticket は 200 だが direct upload 本体 `POST http://localhost:3030/api/v1/gallery/assets` が `{"message":"Unauthorized"}` で 401 となったため、global API prepend や route cache が残る実 runtime でも upload 本体だけは `AuthApiKey` 側で明示的に bypass する保険を戻した。
- frontend の upload 再実行では、同じ fixture を cleanup 後に再送した際に `gallery_assets_user_id_hash_sha256_unique` で 500 になった。原因は soft-deleted asset が DB unique 制約には残る一方、controller の duplicate 判定が active asset のみを見ていたことだったため、同一ハッシュの trashed asset は restore して再利用するよう修正した。
- CORS は `x-upload-token` を常時許可ヘッダへ補完し、`credentials: include` での直 upload に必要な `supports_credentials` を env 経由で有効化可能にした。
- contract ファイルは未変更。今回の修正は runtime の middleware 境界と browser preflight blocker の解消に限定。

## Verification Notes

- Focused PHPUnit で upload-ticket の `x-api-key` 必須維持、upload 本体の no-api-key 成功、invalid/expired/used token の 401、CSRF 失敗、demo.guard 維持、preflight ヘッダを確認する。
- 実ブラウザ確認時は backend の config cache が残っている場合に `config/cors.php` 変更が反映されないため、必要なら `php artisan config:clear` 後に再試験する。
- route cache が残っていると `POST /api/v1/gallery/assets` の middleware 境界変更が反映されず、修正後も no-api-key upload が 401 のままに見えるため、必要なら `php artisan route:clear` も併せて実行する。
- browser 再確認では upload-ticket が 200 のまま、direct upload 本体が 200/201 に変わることを優先確認する。

## Residual Risk

- 実環境の `.env` / `.env.e2e` に独自の `CORS_ALLOWED_HEADERS` や `CORS_SUPPORTS_CREDENTIALS` 上書きがある場合は、その設定が優先される。既定値では直 upload を通すが、環境固有値は別途確認が必要。