#!/usr/bin/env bash
set -euo pipefail

RELEASES_DIR="/virtual/ka2/public_html/pulllog_releases"
CURRENT_LINK="/virtual/ka2/public_html/pulllog_current"

usage() {
    cat <<'USAGE'
Usage: pulllog_rollback.sh [<release-name>]

Without arguments the script reads .previous_release of the current deployment.
USAGE
}

target="${1:-}"

if [[ -z "$target" ]]; then
    if [[ ! -f "$RELEASES_DIR/.current_release" ]]; then
        echo "ERROR: .current_release not found" >&2
        exit 1
    fi
    current_release=$(<"$RELEASES_DIR/.current_release")
    prev_file="$RELEASES_DIR/$current_release/.previous_release"
    if [[ ! -f "$prev_file" ]]; then
        echo "ERROR: $current_release has no .previous_release" >&2
        exit 1
    fi
    target=$(tr -d '\r\n' < "$prev_file")
    if [[ -z "$target" ]]; then
        echo "ERROR: .previous_release is empty" >&2
        exit 1
    fi
fi

target_dir="$RELEASES_DIR/$target"

if [[ ! -d "$target_dir" ]]; then
    echo "ERROR: $target_dir does not exist" >&2
    exit 1
fi

previous=""
if [[ -L "$CURRENT_LINK" ]]; then
    previous=$(basename "$(readlink "$CURRENT_LINK")")
fi

ln -sfn "$target_dir" "$CURRENT_LINK"
echo "$target" > "$RELEASES_DIR/.current_release"

hint=""
if [[ -f "$target_dir/.previous_release" ]]; then
    hint=$(tr -d '\r\n' < "$target_dir/.previous_release")
fi

echo "Rollback completed: $target is now active (previous: ${previous:-none})"
if [[ -n "$hint" ]]; then
    echo "Next rollback candidate: ./pulllog_rollback.sh $hint"
fi