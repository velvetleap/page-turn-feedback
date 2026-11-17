#!/usr/bin/env bash
# Runs every test. Needs: php, python3 with "playwright" and its Chromium (python3 -m playwright install chromium),
# and "subversion" for deploy_test.sh (that test is skipped without it).
set -uo pipefail
cd "$(dirname "$0")" || exit 1
bash make_pages.sh || exit 1

failed=0
run() { echo; echo "=== $*"; "$@" || { echo ">>> FAILED: $*"; failed=1; }; }

PTFB_LANG=en run php settings_test.php
PTFB_LANG=it run php settings_test.php
PTFB_LANG=en run php admin_pages_test.php
PTFB_LANG=it run php admin_pages_test.php
run php compat_test.php
PTFB_EMPTY=1 run php compat_test.php
run php load_test.php
PTFB_AJAX=1 run php load_test.php
PTFB_FRONT=1 run php load_test.php
run python3 behavior_test.py
run python3 admin_js_test.py
run python3 admin_features_test.py
run bash deploy_test.sh

echo
if [ "$failed" -eq 0 ]; then echo "ALL TESTS PASSED"; else echo "SOME TESTS FAILED"; fi
exit "$failed"
