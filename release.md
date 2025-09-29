## PullLog用共有ディレクトリ:
/virtual/ka2/public_html/pulllog_shared/
  ├ .env # 本番用環境設定
  ├ bootstrap/
  └ storage/
      ├ app/
      │   ├ backups/ # DBバックアップダンプファイル格納ディレクトリ（日次バッチからの出力先）
      │   ├ private/ # 
      │   ├ public/  # 公開可永続化ファイル格納用ディレクトリ <- api.pulllog.net/storage/ からシンボリックリンク
      │   │   ├ avatars/ # ユーザー毎のアバター画像
      │   │   └ video/   # デモ動画など（公開アセット用）
      │   └ reports/ # サービスレポートファイル格納ディレクトリ（日次バッチからの出力先）
      └ logs/

## PullLog用リリースディレクトリ:
/virtual/ka2/public_html/pulllog_releases/
  ├ 20250927-1/ # 例: 2025/09/27の1回目リリース用 Git リポジトリ
  └ YYYYMMDD-N/

## PullLog稼働リソース参照シンボリックリンク:
/virtual/ka2/public_html/pulllog_current # 例: -> pulllog_releases/20250927-1

## PullLogバックエンド（api.pulllog.net）のドキュメントルート:
/virtual/ka2/public_html/api.pulllog.net
  ├ .htaccess # URLパスのリライト設定（内容は下記参照）
  ├ beta -> ../pulllog_current/beta/ # 稼働中リリースリポジトリ内の beta ディレクトリへのシンボリックリンク
  ├ storage -> ../pulllog_shared/storage/app/public/ # 共有ディレクトリ内の永続化ファイル格納ディレクトリへのシンボリックリンク
  └ v1 -> ../pulllog_current/stable/public/ # 稼働中リリースリポジトリ内の Laravel アプリの公開ディレクトリへのシンボリックリンク

### .htaccess:
```
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

RewriteRule ^api/v1/(.*)$ v1/api/v1/$1 [L]
```

---

## 前提条件
- composer 2.x、php 8.2 以上、npm 10 以上がサーバ上で利用可能であること。
- `/virtual/ka2/public_html/pulllog_shared/{.env,storage}` の所有権/パーミッションが、Web サーバ・シェルユーザーの双方から読み書きできること。
- SSH ログイン後は `set -euo pipefail` 相当の安全設定を有効化し、すべてのコマンド結果を確認すること。

## リリース手順 (手動)
以下は 2025年9月27日の1回目リリース (`20250927-1`) を例とした手順。必要に応じてリリース名・Git リファレンスを変更してください。

```bash
RELEASE=20250927-1
REF=main
RELEASES=/virtual/ka2/public_html/pulllog_releases
SHARED=/virtual/ka2/public_html/pulllog_shared
CURRENT=/virtual/ka2/public_html/pulllog_current

mkdir -p "$RELEASES"
cd "$RELEASES"
git clone --origin origin https://github.com/magicmethods/pulllog-backend.git "$RELEASE"
cd "$RELEASE"
git fetch origin "$REF"
git checkout "$REF"

ln -sfn "$SHARED/.env" stable/.env
ln -sfn "$SHARED/.env" beta/.env
ln -sfn "$SHARED/storage/logs" stable/storage/logs
ln -sfn "$SHARED/storage/app" stable/storage/app

cd stable
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
# View資産が必要な場合のみ BUILD_FRONTEND=1 を設定して実行
if [ "${BUILD_FRONTEND:-0}" = "1" ]; then
  if command -v npm >/dev/null 2>&1; then
    npm ci --no-audit --no-fund --no-progress
    npm run build
  else
    echo "npm が見つからないためフロント資産ビルドをスキップ" >&2
  fi
fi
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
# スキーマ変更がある場合のみ実行
php artisan migrate --force
cd ..

cd beta
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
cd ..

git rev-parse HEAD > .release_commit
PREVIOUS=$(readlink "$CURRENT" || true)
if [ -n "$PREVIOUS" ]; then
  basename "$PREVIOUS" > .previous_release
fi

echo "$RELEASE" > "$RELEASES/.current_release"
cd /virtual/ka2/public_html
ln -sfn "pulllog_releases/$RELEASE" pulllog_current
```

## ロールバック手順 (手動)
1. 切り戻したいリリース名 (`YYYYMMDD-N`) を特定する。最新のリリース直前へ戻る場合は `pulllog_releases/<現行リリース>/.previous_release` を参照。
2. 下記コマンドで `pulllog_current` のリンク先を更新する。

```bash
cd /virtual/ka2/public_html
TARGET=20250920-1 # 例: 切り戻し先リリース
ln -sfn "pulllog_releases/$TARGET" pulllog_current
echo "$TARGET" > pulllog_releases/.current_release
```

## シェルスクリプトによる運用
リリース作業の定型化のため、`scripts/` 配下に以下のスクリプトを用意しています。

- `scripts/pulllog_release.sh`: リリースのクローン・依存関係インストール・キャッシュ再生成・シンボリックリンク更新を自動化します。
  - 例: `./scripts/pulllog_release.sh --migrate 20250927-1`
  - `--ref <ref>` オプションで特定ブランチ/タグ/コミットを指定可能。
  - `--frontend` を指定したときのみ npm ビルドを実行します（指定しなければスキップ）。
  - `--force` で既存のリリースディレクトリを確認なしで削除してから処理します。
  - `COMPOSER_CMD` / `PHP_CMD` 環境変数で Composer や PHP のパスを上書き可能（`.phar` を指す場合は PHP 経由で実行）。
  - Composer 実行後に `vendor/` が無ければエラー終了し、`pulllog_current` は切り替わりません。
- `scripts/pulllog_rollback.sh`: `.previous_release` または引数で指定したリリースへ即時切り戻し。
  - 例: `./scripts/pulllog_rollback.sh` （直前リリースへ）
  - 例: `./scripts/pulllog_rollback.sh 20250920-1`

スクリプト使用時も、出力ログ・戻り値を必ず確認し、必要に応じてマイグレーションやサービス再起動を実施してください。

## 補足: bootstrap/cache について
`bootstrap/cache/` には `php artisan config:cache` や `route:cache`, `event:cache`, `view:cache` 等で生成されるビルド成果物（`config.php`, `routes-*.php`, `events.php`, `services.php` など）が保存されます。これらはリリースごとのアプリケーションコード・`.env` 内容に依存するため、リリースディレクトリごとに再生成し、共有ディレクトリに置かないのが安全です。アプリケーションユーザー（Web サーバ）が書き込みできる 755/775 程度の権限を確保してください。
