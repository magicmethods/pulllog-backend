#!/usr/bin/env bash
set -euo pipefail

REPO_URL="https://github.com/magicmethods/pulllog-backend.git"
RELEASES_DIR="/virtual/ka2/public_html/pulllog_releases"
SHARED_DIR="/virtual/ka2/public_html/pulllog_shared"
CURRENT_LINK="/virtual/ka2/public_html/pulllog_current"
DEFAULT_REF="main"

usage() {
    cat <<'USAGE'
Usage: pulllog_release.sh [options] <release-name>

Required:
  <release-name>         e.g. 20250927-1 (directory created under pulllog_releases/)

Options:
  --ref <ref>            Git branch/tag/commit to deploy (default: main)
  --migrate              Run php artisan migrate --force after caching
  --skip-frontend        Skip npm ci / npm run build (default)
  --frontend             Run npm ci / npm run build
  --force                Remove existing release directory without confirmation
  --help                 Show this help message

Environment variables:
  COMPOSER_CMD           Composer binary path or PHAR (default: "composer")
  PHP_CMD                PHP binary path (default: "php")
USAGE
}

composer_cmd=${COMPOSER_CMD:-composer}
php_cmd=${PHP_CMD:-php}
ref=$DEFAULT_REF
run_migrations=false
skip_frontend=true
force_replace=false
release_name=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --ref)
            [[ $# -ge 2 ]] || { echo "ERROR: --ref requires a value" >&2; exit 1; }
            ref=$2
            shift 2
            ;;
        --migrate)
            run_migrations=true
            shift
            ;;
        --skip-frontend)
            skip_frontend=true
            shift
            ;;
        --frontend)
            skip_frontend=false
            shift
            ;;
        --force)
            force_replace=true
            shift
            ;;
        --help)
            usage
            exit 0
            ;;
        --)
            shift
            break
            ;;
        -* )
            echo "ERROR: unknown option $1" >&2
            usage
            exit 1
            ;;
        *)
            if [[ -z $release_name ]]; then
                release_name=$1
                shift
                continue
            else
                echo "ERROR: unexpected argument $1" >&2
                usage
                exit 1
            fi
            ;;
    esac
done

if [[ -z $release_name && $# -gt 0 ]]; then
    release_name=$1
    shift
fi

[[ -n $release_name ]] || { usage >&2; exit 1; }

if [[ $# -gt 0 ]]; then
    echo "ERROR: unexpected extra arguments: $*" >&2
    exit 1
fi

target_dir="${RELEASES_DIR}/${release_name}"

if [[ -e $target_dir ]]; then
    if [[ $force_replace == true ]]; then
        rm -rf -- "$target_dir"
    else
        read -r -p "$target_dir already exists. Remove and continue? [y/N] " answer
        case ${answer:-N} in
            [yY])
                rm -rf -- "$target_dir"
                ;;
            *)
                echo "Aborted."
                exit 1
                ;;
        esac
    fi
fi

mkdir -p "$RELEASES_DIR"

echo "[1/7] Cloning repository into $target_dir"
git clone --origin origin "$REPO_URL" "$target_dir"

pushd "$target_dir" >/dev/null
if [[ $ref != $DEFAULT_REF ]]; then
    echo "Checking out $ref"
    git fetch origin "$ref"
    git checkout "$ref"
fi
git submodule update --init --recursive
current_commit=$(git rev-parse HEAD)
popd >/dev/null

echo "[2/7] Linking shared environment file and storage"
ln -sfn "$SHARED_DIR/.env" "$target_dir/stable/.env"
ln -sfn "$SHARED_DIR/.env" "$target_dir/beta/.env"

mkdir -p "$SHARED_DIR/storage/app" "$SHARED_DIR/storage/logs"
ln -sfn "$SHARED_DIR/storage/logs" "$target_dir/stable/storage/logs"
ln -sfn "$SHARED_DIR/storage/app" "$target_dir/stable/storage/app"

chmod -R ug+rw "$target_dir/stable/bootstrap/cache" || true
chmod -R ug+rw "$SHARED_DIR/storage" || true

echo "[3/7] Installing stable dependencies"
pushd "$target_dir/stable" >/dev/null

run_composer() {
    if [[ $composer_cmd == *.phar ]]; then
        "$php_cmd" "$composer_cmd" "$@"
    else
        # shellcheck disable=SC2086
        $composer_cmd "$@"
    fi
}

run_composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [[ ! -d vendor ]]; then
    echo "ERROR: composer install did not produce vendor/" >&2
    exit 1
fi

if [[ $skip_frontend == false && -f package.json ]]; then
    if command -v npm >/dev/null 2>&1; then
        echo "[4/7] Building front-end assets"
        npm ci --no-audit --no-fund --no-progress
        npm run build
    else
        echo "WARNING: npm not found; skipping asset build" >&2
    fi
else
    echo "[4/7] Skipping front-end build"
fi

echo "[5/7] Refreshing Laravel caches"
"$php_cmd" artisan config:clear
"$php_cmd" artisan route:clear
"$php_cmd" artisan view:clear
"$php_cmd" artisan event:clear
"$php_cmd" artisan config:cache
"$php_cmd" artisan route:cache
"$php_cmd" artisan view:cache
"$php_cmd" artisan event:cache

if [[ $run_migrations == true ]]; then
    echo "[5a] Running database migrations"
    "$php_cmd" artisan migrate --force
fi
popd >/dev/null

if [[ -d "$target_dir/beta" ]]; then
    echo "[6/7] Installing beta dependencies"
    pushd "$target_dir/beta" >/dev/null
    run_composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    if [[ ! -d vendor ]]; then
        echo "ERROR: composer install did not produce vendor/ for beta" >&2
        exit 1
    fi
    popd >/dev/null
else
    echo "[6/7] Skipping beta dependencies (directory not present)"
fi

prev_release=""
if [[ -L "$CURRENT_LINK" ]]; then
    prev_target=$(readlink "$CURRENT_LINK" || true)
    if [[ -n $prev_target ]]; then
        prev_release=$(basename "$prev_target")
        echo "$prev_release" > "$target_dir/.previous_release"
    fi
fi

ln -sfn "$target_dir" "$CURRENT_LINK"
echo "$release_name" > "$RELEASES_DIR/.current_release"
echo "$current_commit" > "$target_dir/.release_commit"

echo "[7/7] Release completed: $release_name (commit $current_commit)"
if [[ -n $prev_release ]]; then
    echo "Previous release was: $prev_release"
fi
