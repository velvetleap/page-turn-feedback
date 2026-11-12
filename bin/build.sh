#!/usr/bin/env bash
# Builds the plugin exactly as it is published:
#   build/velvet-leap-page-turn-feedback/       the clean plugin folder
#   build/velvet-leap-page-turn-feedback.zip    the same folder zipped (root folder = velvet-leap-page-turn-feedback)
# Only files tracked by git are used, minus the top-level names listed in .distignore.
set -euo pipefail
cd "$(dirname "$0")/.."

slug="velvet-leap-page-turn-feedback"
rm -rf build
mkdir -p "build/$slug"

mapfile -t skip < <(grep -v -E '^[[:space:]]*(#|$)' .distignore | sed 's#^/##; s#/$##')

count=0
while IFS= read -r -d '' file; do
	top="${file%%/*}"
	for s in "${skip[@]}"; do
		if [[ "$top" == "$s" ]]; then continue 2; fi
	done
	mkdir -p "build/$slug/$(dirname "$file")"
	cp -p "$file" "build/$slug/$file"
	count=$((count + 1))
done < <(git ls-files -z)

(cd build && zip -r -q -X "$slug.zip" "$slug")
echo "Built build/$slug.zip ($count files)"
