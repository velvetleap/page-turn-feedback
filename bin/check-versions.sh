#!/usr/bin/env bash
# Checks that the version number is the same everywhere it is written, and that the name, the slug, the text
# domain and the "Requires at least" value agree (what the WordPress.org review looks at).
# Usage: bin/check-versions.sh [tag]     (with a tag such as v1.7.2 it must match too)
set -uo pipefail
cd "$(dirname "$0")/.."

fail=0
err() { echo "::error::$1"; echo "ERROR: $1"; fail=1; }

slug="velvet-leap-page-turn-feedback"
main="$slug.php"
[[ -f "$main" ]] || { echo "::error::The main plugin file $main does not exist."; echo "ERROR: $main does not exist."; exit 1; }
head_value() { sed -n "s/^[[:space:]]*\*[[:space:]]*$1:[[:space:]]*//p" "$main" | head -1 | sed 's/[[:space:]]*$//'; }

header=$(head_value Version | tr -d '[:space:]')
const=$(sed -n "s/.*define( 'PTFB_VERSION', '\([^']*\)' );.*/\1/p" "$main" | head -1)
stable=$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -1 | tr -d '[:space:]')

if [[ -z "$header" ]]; then err "No 'Version:' line found in the plugin header."; exit 1; fi
v="$header"
echo "Plugin header version: $v"

[[ "$const" == "$v" ]]  || err "PTFB_VERSION is '$const' but the plugin header says '$v'."
[[ "$stable" == "$v" ]] || err "readme.txt 'Stable tag' is '$stable' but the plugin header says '$v'."
grep -q -E "^= ${v//./\\.} =[[:space:]]*$" readme.txt || err "readme.txt has no changelog entry '= $v ='."
grep -q -E "^## ${v//./\\.}( |$)" CHANGELOG.md      || err "CHANGELOG.md has no entry '## $v'."
grep -q "(versione ${v})" LEGGIMI.txt                || err "LEGGIMI.txt does not say '(versione $v)'."
name=$(head_value "Plugin Name")
for f in "languages/$slug.pot" "languages/$slug-it_IT.po"; do
	grep -q "^\"Project-Id-Version: ${name} ${v}" "$f" || err "$f has a different Project-Id-Version than '$name $v'."
done

# Name, slug, text domain
[[ "$(head_value 'Text Domain')" == "$slug" ]] || err "The Text Domain in the plugin header is not '$slug'."
[[ "$(sed -n '1s/^=== \(.*\) ===$/\1/p' readme.txt)" == "$name" ]] || err "The title of readme.txt is not the plugin name '$name'."
bad=$(grep -nE "\b(__|_e|_x|_n|_nx|esc_html__|esc_attr__|esc_html_e|esc_attr_e|esc_html_x|esc_attr_x)\(" "$main" includes/*.php | grep -v "'$slug'" || true)
[[ -z "$bad" ]] || err "Translation calls that do not use the text domain '$slug': $(echo "$bad" | head -3)"
# (comment lines are ignored: the main file explains in a comment why the call is not there)
if grep -rn "load_plugin_textdomain" "$main" includes uninstall.php 2>/dev/null | grep -vE '^[^:]+:[0-9]+:[[:space:]]*(\*|//|/\*|#)' | grep -q .; then
	err "load_plugin_textdomain() is back: the WordPress.org review asked to remove it."
fi
grep -q -E '^/languages[[:space:]]*$' .distignore || err ".distignore must list /languages: translation files stay out of the zip."

# Requires at least: a stable major version (5.1, 6.2...), the same in the header and in the readme
req_h=$(head_value "Requires at least")
req_r=$(sed -n 's/^Requires at least:[[:space:]]*//p' readme.txt | head -1 | sed 's/[[:space:]]*$//')
[[ "$req_h" =~ ^[0-9]+\.[0-9]+$ ]] || err "Requires at least in the plugin header ('$req_h') must be a major version like 5.1, without a third number."
[[ "$req_h" == "$req_r" ]] || err "Requires at least differs: header '$req_h', readme.txt '$req_r'."

if [[ $# -ge 1 && -n "$1" ]]; then
	tag="${1#v}"
	[[ "$tag" == "$v" ]] || err "The tag is '$1' but the plugin version is '$v'."
fi

if [[ $fail -eq 0 ]]; then echo "OK: version $v is consistent everywhere."; fi
exit $fail
