#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_FILE="$ROOT_DIR/muguerza-checkout.php"

usage() {
    cat <<'USAGE'
Usage:
  scripts/bump-plugin-version.sh patch
  scripts/bump-plugin-version.sh minor
  scripts/bump-plugin-version.sh major
  scripts/bump-plugin-version.sh 1.2.3
USAGE
}

if [[ $# -ne 1 ]]; then
    usage
    exit 1
fi

INPUT="$1"
CURRENT_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$PLUGIN_FILE" | head -n 1 | tr -d '[:space:]')"

if [[ -z "$CURRENT_VERSION" ]]; then
    echo "Could not read current version from $PLUGIN_FILE" >&2
    exit 1
fi

if [[ ! "$CURRENT_VERSION" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
    echo "Current version '$CURRENT_VERSION' is not semantic x.y.z" >&2
    exit 1
fi

major="${BASH_REMATCH[1]}"
minor="${BASH_REMATCH[2]}"
patch="${BASH_REMATCH[3]}"

case "$INPUT" in
    patch)
        NEW_VERSION="${major}.${minor}.$((patch + 1))"
        ;;
    minor)
        NEW_VERSION="${major}.$((minor + 1)).0"
        ;;
    major)
        NEW_VERSION="$((major + 1)).0.0"
        ;;
    *)
        if [[ ! "$INPUT" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            echo "Invalid version '$INPUT'. Use patch, minor, major, or x.y.z" >&2
            exit 1
        fi
        NEW_VERSION="$INPUT"
        ;;
esac

perl -0pi -e "s/^ \* Version:\h*\Q$CURRENT_VERSION\E/ * Version: $NEW_VERSION/m" "$PLUGIN_FILE"

echo "Version updated: $CURRENT_VERSION -> $NEW_VERSION"
