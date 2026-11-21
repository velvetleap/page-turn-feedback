#!/usr/bin/env bash
# Builds a throw-away WordPress site (SQLite, no MySQL needed) with this plugin and sample feedback.
# It is used to take the screenshots of the WordPress.org plugin page (design/screenshots.py).
#
# Usage: design/wp-rig.sh <wordpress-dir> <sqlite-repo-dir> <site-dir>
#   <wordpress-dir>  checkout of WordPress core:
#                    git clone --depth 1 --branch 7.1.4 https://github.com/WordPress/wordpress <wordpress-dir>
#   <sqlite-repo-dir> checkout of the SQLite Database Integration plugin:
#                    git clone --depth 1 https://github.com/WordPress/sqlite-database-integration <sqlite-repo-dir>
#   <site-dir>       where the site is built (deleted first)
# Then: php -S localhost:8080 -t <site-dir>   (admin: admin / password)
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
wp_dir="$(cd "$1" && pwd)"
sq_dir="$(cd "$2" && pwd)"
site="$3"

rm -rf "$site"
mkdir -p "$site"
tar -C "$wp_dir" --exclude=.git -cf - . | tar -C "$site" -xf -
site="$(cd "$site" && pwd)"

# SQLite plugin: the package with the driver symlink resolved, and the db.php drop-in.
plug="$site/wp-content/plugins/sqlite-database-integration"
cp -R "$sq_dir/packages/plugin-sqlite-database-integration" "$plug"
rm "$plug/wp-includes/database"
cp -R "$sq_dir/packages/mysql-on-sqlite/src" "$plug/wp-includes/database"
cp "$plug/db.copy" "$site/wp-content/db.php"
sed -i "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$plug#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$site/wp-content/db.php"
mkdir -p "$site/wp-content/database"

# The plugin exactly as it is published (what ends up in the zip).
(cd "$root" && bash bin/build.sh >/dev/null)
cp -R "$root/build/velvet-leap-page-turn-feedback" "$site/wp-content/plugins/velvet-leap-page-turn-feedback"

cat > "$site/wp-config.php" <<'PHP'
<?php
define( 'DB_NAME', 'wp' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'WP_HOME', 'http://localhost:8080' );
define( 'WP_SITEURL', 'http://localhost:8080' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
define( 'DISALLOW_FILE_MODS', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
define( 'AUTH_KEY', 'rig' ); define( 'SECURE_AUTH_KEY', 'rig' ); define( 'LOGGED_IN_KEY', 'rig' ); define( 'NONCE_KEY', 'rig' );
define( 'AUTH_SALT', 'rig' ); define( 'SECURE_AUTH_SALT', 'rig' ); define( 'LOGGED_IN_SALT', 'rig' ); define( 'NONCE_SALT', 'rig' );
$table_prefix = 'wp_';
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHP

php "$root/design/wp-rig-seed.php" "$site"
echo "Site ready in $site. Start it with: php -S localhost:8080 -t $site"
