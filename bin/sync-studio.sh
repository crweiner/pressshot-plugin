#!/usr/bin/env bash
# Copy the plugin into the local Studio site and activate it.
set -eu
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STUDIO="${STUDIO_BIN:-studio}"
SITE="${PRESSSHOT_STUDIO_SITE:-$HOME/Studio/pressshot}"
DEST="$SITE/wp-content/plugins/pressshot/"

mkdir -p "$DEST"
rsync -a --delete --exclude .git --exclude node_modules --exclude tests --exclude src "$ROOT/" "$DEST"
"$STUDIO" wp --path "$SITE" plugin activate pressshot
