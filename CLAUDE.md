# Page Turn Feedback: working notes

WordPress plugin that shows feedback (testimonials, quotes) one at a time on a card that turns like the page of a book. Published on GitHub (`velvetleap/page-turn-feedback`) and submitted to the WordPress.org plugin directory. Author/organisation: Velvet Leap. Copyright holder: Mattia Scudellaro.

The maintainer is not a developer. Talk to him in Italian, in plain words, short. Say what changed and what he has to decide; never ask him to run commands.

## How to work

- Never push to `main`. Work on a branch `claude/<topic>` and push it: `auto-pr.yml` opens the pull request by itself and `ci.yml` runs on the push. (This needs one setting, switched on once by the maintainer: Settings > Actions > General > Workflow permissions > "Allow GitHub Actions to create and approve pull requests".) Read the result without any login: `https://api.github.com/repos/velvetleap/page-turn-feedback/pulls?head=velvetleap:claude/<topic>` for the pull request number and `.../commits/<sha>/check-runs` for the checks; tell the maintainer the result in Italian.
- The maintainer only answers yes or no in the chat. On a clear yes, merge by pushing a branch `claude/approve-pr-<number>` that points at the exact head commit of the pull request (`git push origin <sha>:refs/heads/claude/approve-pr-<number>`). `approve.yml` merges it (merge commit) only if that commit is still the head, the pull request targets `main` and every check is green, then deletes the pull request branch and the approve branch. Never push it without his explicit yes, and never while a check is red or running.
- Releases: after the merge, on a clear yes, push `main` to a branch `claude/release-X.Y.Z` (`git fetch origin main && git push origin origin/main:refs/heads/claude/release-X.Y.Z`). `release-on-approval.yml` checks the version everywhere, builds the zip, publishes the release `vX.Y.Z` with the zip and the notes from `CHANGELOG.md`, and deletes the branch. Publishing a release by hand on GitHub also works (`release.yml` attaches the zip).
- WordPress.org upload is separate and only starts after the plugin is approved. It must need a manual approval click by the maintainer (GitHub environment with a required reviewer). Never ask for, store or print any password or token: secrets are typed by the maintainer directly into GitHub.
- Branches cannot be deleted from an AI session (the connection answers 403): the workflows above delete what they merge or release. Any other leftover branch: ask the maintainer to delete it, or leave it.
- Run every test before pushing: `bash tests/run.sh` (needs `cd tests && npm ci` once, plus Python `playwright` with Chromium). Also run `vendor/bin/phpcs` (after `composer install`) and `bash bin/check-versions.sh`.

## Rules for this project

- Copyright line stays "Mattia Scudellaro"; author in the header stays "Velvet Leap"; license GPL-2.0-or-later.
- No contact details for reporting problems in the README or readme.txt for now.
- Prefix everything with `ptfb` (functions, options, hooks, constants), CSS classes `ptfb__*`, CSS variables `--ptfb-*`. Do not rename identifiers: existing sites store settings under them.
- Strings are written in English with the text domain `page-turn-feedback`, and the Italian translation is kept in step: after any string change put the Italian text of the new strings in a JSON file and run `python3 bin/i18n.py --new file.json` (needs `pip install polib`): it rebuilds `languages/page-turn-feedback.pot`, `-it_IT.po` and `-it_IT.mo` from all the PHP files. The tests read the real `.mo`.
- `load_plugin_textdomain()` is kept on purpose (without it WordPress does not find the bundled translation). The Plugin Check warning about it is known and accepted.
- Escape late: output is escaped where it is printed. No remote requests, no cookies, no tracking, no AJAX or REST endpoints.
- The look on sites using the Fascinate theme must not change: with that theme the markup keeps the theme's own `fb-container` class. Check `tests/compat_test.php` and the visual tests when touching markup or CSS.
- Long words and web addresses must wrap inside the card, for every text alignment (covered by `tests/behavior_test.py`).
- Keep names of the maintainer's clients, sites and private matters out of this public repository.
- Decided not to add (the maintainer said no): extra fields on a feedback, groups or several lists, a block for the block editor.

## Version bump checklist

Plugin header `Version`, `PTFB_VERSION` in `page-turn-feedback.php`, `Stable tag` and a new `= X.Y.Z =` changelog entry in `readme.txt`, a new `## X.Y.Z` entry in `CHANGELOG.md`, `(versione X.Y.Z)` in `LEGGIMI.txt`, `Project-Id-Version` in the `.pot` and `.po` (then recompile the `.mo`). `bash bin/check-versions.sh` verifies all of them.

## Layout

- `page-turn-feedback.php`, `uninstall.php`, `includes/`, `assets/`, `languages/`, `readme.txt`, `LEGGIMI.txt`: the plugin (what ends up in the zip). `includes/` is loaded only in the dashboard: `presets.php` (ready-made styles), `ordering.php` (Order page), `tools.php` (CSV import and export).
- `bin/`: `build.sh` (builds `build/page-turn-feedback.zip` honoring `.distignore`), `check-versions.sh`, `i18n.py` (rebuilds the translation files).
- `tests/`: stubs that run the plugin outside WordPress, browser tests with Playwright, test theme in `tests/fixtures/`.
- `.github/workflows/`: `ci.yml`, `auto-pr.yml`, `approve.yml`, `release-on-approval.yml`, `release.yml`.
- Everything else at the root (README, CHANGELOG, LICENSE, composer.json, phpcs.xml.dist, CLAUDE.md) stays out of the zip.
