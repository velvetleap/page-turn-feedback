#!/usr/bin/env bash
# Tests bin/deploy-wporg.sh against a Subversion repository created on the fly (file://).
# It never talks to WordPress.org. Needs "svn" and "svnadmin" (package "subversion"); without them the test is skipped.
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

if ! command -v svn >/dev/null 2>&1 || ! command -v svnadmin >/dev/null 2>&1; then
	echo "SKIP: Subversion is not installed"
	exit 0
fi

slug="velvet-leap-page-turn-feedback"
fails=0
ok() { echo "PASS $1"; }
bad() { echo "FAIL $1"; fails=1; }
check() { local name="$1"; shift; if "$@"; then ok "$name"; else bad "$name"; fi; }
# no() and has() are called through check(), which shellcheck does not follow.
# shellcheck disable=SC2329
no() { ! "$@"; }

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

bash bin/build.sh >/dev/null
plugin="$PWD/build/$slug"
version="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$plugin/$slug.php" | head -1 | tr -d '[:space:]')"

# A repository like the one WordPress.org gives: empty trunk, tags, assets and branches.
mkrepo() {
	svnadmin create "$1"
	svn mkdir -q -m "init" "file://$1/trunk" "file://$1/tags" "file://$1/assets" "file://$1/branches"
}
rev() { svn info --show-item revision "file://$1"; }
deploy() { # repo, then the script arguments
	local repo="$1"; shift
	SVN_URL="file://$repo" ASSETS_DIR="${ASSETS_DIR:-$tmp/no-assets}" bash bin/deploy-wporg.sh "$@" 2>&1
}
# shellcheck disable=SC2329
has() { svn ls "file://$1/$2" >/dev/null 2>&1; }

echo "== dry run publishes nothing"
repo="$tmp/r1"; mkrepo "$repo"
before="$(rev "$repo")"
out="$(deploy "$repo" "$version" "$plugin" --dry-run)"; code=$?
check "dry run succeeds" test "$code" -eq 0
check "dry run says nothing was published" grep -q "Dry run: nothing was published" <<<"$out"
check "dry run leaves the repository at the same revision" test "$(rev "$repo")" = "$before"
check "dry run does not need credentials" no grep -q "SVN_USERNAME" <<<"$out"

echo "== publish"
secret="Sup3rS3cretPassw0rd"
out="$(SVN_USERNAME=someone SVN_PASSWORD="$secret" deploy "$repo" "$version" "$plugin")"; code=$?
check "publish succeeds" test "$code" -eq 0
check "exactly one new revision" test "$(rev "$repo")" = "$((before + 1))"
check "trunk has the main file" has "$repo" "trunk/$slug.php"
check "trunk has the readme" has "$repo" "trunk/readme.txt"
check "the tag exists with the main file" has "$repo" "tags/$version/$slug.php"
check "no languages folder anywhere" no has "$repo" "trunk/languages"
svn export -q "file://$repo/trunk" "$tmp/exp_trunk"
svn export -q "file://$repo/tags/$version" "$tmp/exp_tag"
check "trunk is exactly the plugin folder" diff -r "$plugin" "$tmp/exp_trunk"
check "the tag is exactly the plugin folder" diff -r "$plugin" "$tmp/exp_tag"
check "the password is not printed" no grep -q "$secret" <<<"$out"

echo "== a published version is never overwritten"
before="$(rev "$repo")"
out="$(SVN_USERNAME=someone SVN_PASSWORD="$secret" deploy "$repo" "$version" "$plugin")"; code=$?
check "second publish of the same version fails" test "$code" -ne 0
check "it explains that the tag exists" grep -q "already has tags/$version" <<<"$out"
check "the repository is untouched" test "$(rev "$repo")" = "$before"

echo "== stale files are removed, assets get their type, unchanged files are not rewritten"
repo="$tmp/r2"; mkrepo "$repo"
seed="$tmp/seed"; mkdir -p "$seed/trunk" "$seed/assets"
cp -a "$plugin/." "$seed/trunk/"
echo "<?php // old" >"$seed/trunk/old-file.php"
mkdir -p "$seed/trunk/gone/deep"; echo "<?php // old" >"$seed/trunk/gone/deep/x.php"
printf 'x' >"$seed/assets/old.png"
svn import -q -m "previous version" "$seed/trunk" "file://$repo/trunk" --force
svn import -q -m "old assets" "$seed/assets" "file://$repo/assets" --force
assets="$tmp/assets"; mkdir -p "$assets"
printf '\211PNG\r\n\032\n' >"$assets/icon-256x256.png"
printf '<svg xmlns="http://www.w3.org/2000/svg"/>' >"$assets/icon.svg"
out="$(ASSETS_DIR="$assets" deploy "$repo" "$version" "$plugin" --dry-run)"
check "dry run lists the stale file for deletion" grep -qE '^ +D +trunk/old-file.php' <<<"$out"
check "dry run lists the stale folder for deletion" grep -qE '^ +D +trunk/gone' <<<"$out"
check "unchanged files are not touched" no grep -qE "^ +[AM] +trunk/$slug.php" <<<"$out"
out="$(ASSETS_DIR="$assets" SVN_USERNAME=someone SVN_PASSWORD="$secret" deploy "$repo" "$version" "$plugin")"; code=$?
check "publish with assets succeeds" test "$code" -eq 0
check "stale file is gone" no has "$repo" "trunk/old-file.php"
check "stale folder is gone" no has "$repo" "trunk/gone"
check "old asset is gone" no has "$repo" "assets/old.png"
check "new icon is in assets" has "$repo" "assets/icon-256x256.png"
check "png has the image/png type" test "$(svn propget svn:mime-type "file://$repo/assets/icon-256x256.png")" = "image/png"
check "svg has the image/svg+xml type" test "$(svn propget svn:mime-type "file://$repo/assets/icon.svg")" = "image/svg+xml"
check "assets are not copied into trunk" no has "$repo" "trunk/icon-256x256.png"

echo "== images only: assets change, trunk and tags are not touched, no new version"
repo="$tmp/r2"                      # the repository of the previous section: a published version and its assets
assets2="$tmp/assets2"; mkdir -p "$assets2"
printf '\211PNG\r\n\032\nB' >"$assets2/banner-772x250.png"
printf 'GIF89a' >"$assets2/icon-128x128.gif"
mkdir -p "$assets2/blueprints"; printf '{"landingPage":"/"}\n' >"$assets2/blueprints/blueprint.json"
before="$(rev "$repo")"
out="$(ASSETS_DIR="$assets2" deploy "$repo" --assets-only --dry-run)"; code=$?
check "images only, dry run: succeeds" test "$code" -eq 0
check "images only, dry run: says nothing was published" grep -q "Dry run: nothing was published" <<<"$out"
check "images only, dry run: lists the new banner" grep -qE '^ +A +assets/banner-772x250.png' <<<"$out"
check "images only, dry run: does not touch trunk or tags" no grep -qE '(trunk|tags)/' <<<"$out"
check "images only, dry run: repository untouched" test "$(rev "$repo")" = "$before"
trunk_before="$(svn cat "file://$repo/trunk/readme.txt" | md5sum)"
out="$(ASSETS_DIR="$assets2" SVN_USERNAME=someone SVN_PASSWORD="$secret" deploy "$repo" --assets-only)"; code=$?
check "images only: publish succeeds" test "$code" -eq 0
check "images only: exactly one new revision" test "$(rev "$repo")" = "$((before + 1))"
changed="$(svn log -v -r "$(rev "$repo")" "file://$repo")"
check "images only: the revision touches assets" grep -q "/assets/" <<<"$changed"
check "images only: the revision does not touch trunk" no grep -q "/trunk/" <<<"$changed"
check "images only: the revision does not touch tags" no grep -q "/tags/" <<<"$changed"
check "images only: the commit message says so" grep -q "Update plugin assets" <<<"$changed"
check "images only: trunk file is the same" test "$(svn cat "file://$repo/trunk/readme.txt" | md5sum)" = "$trunk_before"
check "images only: the tag is still there" has "$repo" "tags/$version/$slug.php"
check "images only: new gif is there with its type" test "$(svn propget svn:mime-type "file://$repo/assets/icon-128x128.gif")" = "image/gif"
check "images only: new banner is there with its type" test "$(svn propget svn:mime-type "file://$repo/assets/banner-772x250.png")" = "image/png"
check "images only: assets not in the folder are removed" no has "$repo" "assets/icon-256x256.png"
check "images only: the live preview blueprint goes to assets/blueprints" has "$repo" "assets/blueprints/blueprint.json"
check "images only: the blueprint is not copied into trunk" no has "$repo" "trunk/blueprints/blueprint.json"
before="$(rev "$repo")"
out="$(ASSETS_DIR="$assets2" SVN_USERNAME=someone SVN_PASSWORD="$secret" deploy "$repo" --assets-only)"; code=$?
check "images only: same images again succeeds" test "$code" -eq 0
check "images only: same images again says nothing to publish" grep -q "nothing to publish" <<<"$out"
check "images only: same images again makes no revision" test "$(rev "$repo")" = "$before"

echo "== refusals before anything is uploaded"
repo="$tmp/r3"; mkrepo "$repo"
before="$(rev "$repo")"
out="$(deploy "$repo" "$version" "$plugin")"; code=$?
check "no credentials: refuses" test "$code" -ne 0
check "no credentials: says which secrets are missing" grep -q "SVN_USERNAME and SVN_PASSWORD" <<<"$out"
out="$(SVN_USERNAME=u SVN_PASSWORD=p deploy "$repo" "9.9.9" "$plugin")"; code=$?
check "wrong version: refuses" test "$code" -ne 0
check "wrong version: says what the header has" grep -q "plugin header says version" <<<"$out"
out="$(SVN_USERNAME=u SVN_PASSWORD=p deploy "$repo" "not-a-version" "$plugin")"; code=$?
check "bad version text: refuses" test "$code" -ne 0
cp -a "$plugin" "$tmp/with-mo"; echo x >"$tmp/with-mo/$slug-it_IT.mo"
out="$(SVN_USERNAME=u SVN_PASSWORD=p deploy "$repo" "$version" "$tmp/with-mo")"; code=$?
check "package with a .mo file: refuses" test "$code" -ne 0
check "package with a .mo file: says why" grep -q "translation files" <<<"$out"
out="$(SVN_USERNAME=u SVN_PASSWORD=p deploy "$repo" --assets-only)"; code=$?
check "images only, no assets folder: refuses" test "$code" -ne 0
check "images only, no assets folder: says so" grep -q "does not exist" <<<"$out"
out="$(ASSETS_DIR="$assets2" deploy "$repo" --assets-only)"; code=$?
check "images only, no credentials: refuses" test "$code" -ne 0
out="$(ASSETS_DIR="$assets2" SVN_USERNAME=u SVN_PASSWORD=p deploy "$repo" "$version" --assets-only)"; code=$?
check "images only with a version typed: refuses" test "$code" -ne 0
out="$(SVN_USERNAME=u SVN_PASSWORD=p deploy "$repo" "$version" "$plugin" --whatever)"; code=$?
check "unknown option: refuses" test "$code" -ne 0
check "none of the refusals touched the repository" test "$(rev "$repo")" = "$before"

echo
if [[ "$fails" -eq 0 ]]; then echo "deploy_test: ALL OK"; else echo "deploy_test: FAILURES"; fi
exit "$fails"
