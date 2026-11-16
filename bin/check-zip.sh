#!/usr/bin/env bash
# Fails unless the release ZIP holds the built Library app. Usage: bin/check-zip.sh [path/to.zip]
# Defaults to pressshot-plugin.zip, the file `npm run plugin-zip` writes.
set -u
ZIP="${1:-$(cd "$(dirname "$0")/.." && pwd)/pressshot-plugin.zip}"
if [ ! -f "$ZIP" ]; then
	echo "FAIL $ZIP doesn't exist"
	exit 1
fi
listing="$(unzip -Z1 "$ZIP")"
status=0
for required in pressshot/pressshot.php pressshot/build/library.js pressshot/build/library.asset.php pressshot/build/share-view-editor.js pressshot/build/library-editor.js pressshot/assets/videopress-pass.js; do
	if printf '%s\n' "$listing" | grep -qx "$required"; then
		echo "PASS $required is in $(basename "$ZIP")"
	else
		echo "FAIL $required is missing from $(basename "$ZIP")"
		status=1
	fi
done
exit "$status"
