#!/usr/bin/env bash
# Checks that the version number is the same everywhere it is written.
# Usage: bin/check-versions.sh [tag]     (with a tag such as v1.7.2 it must match too)
set -uo pipefail
cd "$(dirname "$0")/.."

fail=0
err() { echo "::error::$1"; echo "ERROR: $1"; fail=1; }

header=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' page-turn-feedback.php | head -1 | tr -d '[:space:]')
const=$(sed -n "s/.*define( 'PTFB_VERSION', '\([^']*\)' );.*/\1/p" page-turn-feedback.php | head -1)
stable=$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -1 | tr -d '[:space:]')

if [[ -z "$header" ]]; then err "No 'Version:' line found in the plugin header."; exit 1; fi
v="$header"
echo "Plugin header version: $v"

[[ "$const" == "$v" ]]  || err "PTFB_VERSION is '$const' but the plugin header says '$v'."
[[ "$stable" == "$v" ]] || err "readme.txt 'Stable tag' is '$stable' but the plugin header says '$v'."
grep -q -E "^= ${v//./\\.} =[[:space:]]*$" readme.txt || err "readme.txt has no changelog entry '= $v ='."
grep -q -E "^## ${v//./\\.}( |$)" CHANGELOG.md      || err "CHANGELOG.md has no entry '## $v'."
grep -q "(versione ${v})" LEGGIMI.txt                || err "LEGGIMI.txt does not say '(versione $v)'."
for f in languages/page-turn-feedback.pot languages/page-turn-feedback-it_IT.po; do
	grep -q "^\"Project-Id-Version: Page Turn Feedback ${v}" "$f" || err "$f has a different Project-Id-Version than $v."
done

if [[ $# -ge 1 && -n "$1" ]]; then
	tag="${1#v}"
	[[ "$tag" == "$v" ]] || err "The tag is '$1' but the plugin version is '$v'."
fi

if [[ $fail -eq 0 ]]; then echo "OK: version $v is consistent everywhere."; fi
exit $fail
