#!/usr/bin/env bash
# Publishes one version of the plugin to the WordPress.org plugin repository (Subversion):
#   trunk/            the plugin files of this version
#   tags/<version>/   a copy of trunk, which is what WordPress.org serves as the stable version
#   assets/           icon, banner and screenshots from the .wordpress-org folder (only if the folder exists)
#
# Usage: bin/deploy-wporg.sh <version> <plugin-folder> [--dry-run]
#   <plugin-folder>  the unzipped release (the folder inside velvet-leap-page-turn-feedback.zip), so what is
#                    uploaded is exactly what was released on GitHub.
#   --dry-run        does everything except the commit and prints what would change.
#
# Environment:
#   SVN_USERNAME, SVN_PASSWORD   WordPress.org credentials (needed to publish, not for --dry-run).
#                                The password goes to Subversion through stdin, never on the command line.
#   SVN_URL                      the repository (default: the plugin's WordPress.org repository; tests use file://).
#   ASSETS_DIR                   the assets folder (default: .wordpress-org in the repository).
#
# A tag that already exists is never overwritten: WordPress.org tags are releases.
set -euo pipefail

slug="velvet-leap-page-turn-feedback"
repo_root="$(cd "$(dirname "$0")/.." && pwd)"

die() { echo "::error::$*" >&2; echo "ERROR: $*" >&2; exit 1; }

version="${1:-}"
plugin_dir="${2:-}"
dry_run=0
[[ "${3:-}" == "--dry-run" ]] && dry_run=1
[[ -n "$version" && -n "$plugin_dir" ]] || die "Usage: bin/deploy-wporg.sh <version> <plugin-folder> [--dry-run]"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "'$version' is not a version like 1.9.0."
[[ -d "$plugin_dir" ]] || die "The plugin folder '$plugin_dir' does not exist."
plugin_dir="$(cd "$plugin_dir" && pwd)"

svn_url="${SVN_URL:-https://plugins.svn.wordpress.org/$slug}"
assets_dir="${ASSETS_DIR:-$repo_root/.wordpress-org}"

command -v svn >/dev/null 2>&1 || die "Subversion (svn) is not installed."

# --- The folder really is this version of the plugin ---
main="$plugin_dir/$slug.php"
[[ -f "$main" ]] || die "$slug.php is not in the plugin folder."
[[ -f "$plugin_dir/readme.txt" ]] || die "readme.txt is not in the plugin folder."
header_version="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$main" | head -1 | tr -d '[:space:]')"
stable_tag="$(sed -n 's/^Stable tag:[[:space:]]*//p' "$plugin_dir/readme.txt" | head -1 | tr -d '[:space:]')"
[[ "$header_version" == "$version" ]] || die "The plugin header says version '$header_version', not '$version'."
[[ "$stable_tag" == "$version" ]] || die "The readme.txt Stable tag is '$stable_tag', not '$version'."
if [[ -n "$(find "$plugin_dir" \( -name '*.po' -o -name '*.mo' -o -name '*.pot' \) -print -quit)" ]]; then
	die "The package has translation files: the WordPress.org review asked to leave them out."
fi

# --- Credentials (only to publish) ---
if [[ "$dry_run" -eq 0 ]]; then
	[[ -n "${SVN_USERNAME:-}" && -n "${SVN_PASSWORD:-}" ]] || die "SVN_USERNAME and SVN_PASSWORD are not set. They must be secrets of the GitHub environment 'wordpress-org'."
fi
svn_opts=(--non-interactive --no-auth-cache)

# --- A tag is never overwritten ---
if svn ls "${svn_opts[@]}" "$svn_url/tags/$version" >/dev/null 2>&1; then
	die "WordPress.org already has tags/$version. A published version is never overwritten: use a new version number."
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
wc="$work/wc"

echo "Checking out $svn_url"
svn checkout "${svn_opts[@]}" --quiet --depth immediates "$svn_url" "$wc"
cd "$wc"
for d in trunk tags assets; do
	[[ -d "$d" ]] || svn mkdir --quiet "$d"
done
svn update "${svn_opts[@]}" --quiet --set-depth infinity trunk assets

# Makes <target> an exact copy of <source> inside the working copy: new files added, missing files removed.
sync_dir() {
	local source="$1" target="$2" path
	find "$target" -mindepth 1 -maxdepth 1 -not -name .svn -exec rm -rf {} +
	cp -a "$source/." "$target/"
	svn add --quiet --force --depth infinity "$target"
	svn status "$target" | sed -n 's/^![[:space:]]*//p' | while IFS= read -r path; do
		[[ -e "$path" ]] || svn rm --quiet --force "$path" 2>/dev/null || true
	done
}

echo "Updating trunk"
sync_dir "$plugin_dir" trunk

if [[ -d "$assets_dir" ]]; then
	echo "Updating assets from $assets_dir"
	sync_dir "$assets_dir" assets
	while IFS= read -r -d '' f; do
		case "${f,,}" in
			*.png) mime="image/png" ;;
			*.jpg | *.jpeg) mime="image/jpeg" ;;
			*.gif) mime="image/gif" ;;
			*.svg) mime="image/svg+xml" ;;
			*) continue ;;
		esac
		svn propset --quiet svn:mime-type "$mime" "$f"
	done < <(find assets -type f -print0)
else
	echo "No assets folder ($assets_dir): assets left as they are on WordPress.org."
fi

echo "Creating tags/$version from trunk"
svn copy --quiet trunk "tags/$version"

echo
echo "Changes to publish:"
svn status trunk assets "tags/$version" | sed -e 's/^/  /' | head -200

if [[ "$dry_run" -eq 1 ]]; then
	echo
	echo "Dry run: nothing was published."
	exit 0
fi

echo
echo "Publishing version $version"
printf '%s' "$SVN_PASSWORD" | svn commit "${svn_opts[@]}" --username "$SVN_USERNAME" --password-from-stdin \
	-m "Release $version" trunk tags assets

svn ls "${svn_opts[@]}" "$svn_url/tags/$version" >/dev/null || die "The new tag is not on WordPress.org after the commit."
echo "Published: https://wordpress.org/plugins/$slug/ (version $version)"
