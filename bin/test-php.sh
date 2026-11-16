#!/usr/bin/env bash
# Run PHP integration tests through Studio WP-CLI. With no arguments, runs every tests/php/*-test.php.
# Exits non-zero if any output line starts with FAIL or a test exits with an error.
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STUDIO="${STUDIO_BIN:-studio}"
SITE="${PRESSSHOT_STUDIO_SITE:-$HOME/Studio/pressshot}"

if [ "$#" -gt 0 ]; then
	files=()
	for arg in "$@"; do
		files+=("$(cd "$(dirname "$arg")" && pwd)/$(basename "$arg")")
	done
else
	files=("$ROOT"/tests/php/*-test.php)
fi

status=0
for file in "${files[@]}"; do
	echo "== $(basename "$file")"
	output="$("$STUDIO" wp --path "$SITE" eval-file "$file" 2>&1)"
	code=$?
	printf '%s\n' "$output"
	if [ "$code" -ne 0 ] || printf '%s\n' "$output" | grep -q '^FAIL'; then
		status=1
	fi
done
exit "$status"
