#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Find plugin main file by locating the first PHP file with a Plugin Name header.
MAIN_FILE="$(grep -Rl --include='*.php' -m1 '^\s*\*\s*Plugin Name:' "$ROOT_DIR" || true)"
if [[ -z "$MAIN_FILE" ]]; then
  echo "Errore: file principale del plugin non trovato." >&2
  exit 1
fi

PLUGIN_DIR="$(dirname "$MAIN_FILE")"
PLUGIN_BASENAME="$(basename "$PLUGIN_DIR")"

# Extract version from plugin header if present.
VERSION="$(grep -E "^[[:space:]]*\*+[[:space:]]*Version:" "$MAIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
if [[ -z "$VERSION" ]]; then
  VERSION="0.0.0"
fi

SLUG="$(echo "$PLUGIN_BASENAME" | tr '[:upper:]' '[:lower:]')"

DIST_DIR="$ROOT_DIR/dist"
mkdir -p "$DIST_DIR"

ZIP_NAME="${SLUG}-v${VERSION}.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"

# Patterns to exclude from the archive relative to the plugin directory root.
EXCLUDES=(
  "$PLUGIN_BASENAME/.git/*"
  "$PLUGIN_BASENAME/.github/*"
  "$PLUGIN_BASENAME/node_modules/*"
  "$PLUGIN_BASENAME/vendor/*"
  "$PLUGIN_BASENAME/tests/*"
  "$PLUGIN_BASENAME/docs/*"
  "$PLUGIN_BASENAME/dist/*"
  "$PLUGIN_BASENAME/.vscode/*"
  "$PLUGIN_BASENAME/.idea/*"
  "$PLUGIN_BASENAME/composer.lock"
  "$PLUGIN_BASENAME/package-lock.json"
)

cd "$(dirname "$PLUGIN_DIR")"
rm -f "$ZIP_PATH"
zip -r "$ZIP_PATH" "$PLUGIN_BASENAME" -x "${EXCLUDES[@]}"

echo "OK: creato $ZIP_PATH"
