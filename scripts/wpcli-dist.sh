#!/bin/sh

set -eu

WP_CLI_HOME_DIR="${HOME:-/tmp/wp-cli}"
WP_CLI_PHAR="$WP_CLI_HOME_DIR/wp-cli-nightly.phar"
WP_CLI_PHAR_URL="https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar"
DIST_ARCHIVE_PACKAGE="wp-cli/dist-archive-command"

TARGET_ZIP="${1:-}"

if [ -z "$TARGET_ZIP" ]; then
    echo "Usage: scripts/wpcli-dist.sh path/to/archive.zip" >&2
    exit 1
fi

mkdir -p "$WP_CLI_HOME_DIR"

if [ ! -f "$WP_CLI_PHAR" ]; then
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL "$WP_CLI_PHAR_URL" -o "$WP_CLI_PHAR"
    else
        wget -qO "$WP_CLI_PHAR" "$WP_CLI_PHAR_URL"
    fi
fi

if ! php "$WP_CLI_PHAR" package list --format=csv 2>/dev/null | grep -q "$DIST_ARCHIVE_PACKAGE"; then
    php "$WP_CLI_PHAR" package install "${DIST_ARCHIVE_PACKAGE}:@stable"
fi

php "$WP_CLI_PHAR" dist-archive . "$TARGET_ZIP" --create-target-dir
