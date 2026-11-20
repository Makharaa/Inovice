#!/usr/bin/env bash
#
# Updates this installation from GitHub (fast-forward only) and tells how to roll back.
#
#   bash tools/update.sh
#
# Run it as the web-site user (the owner of the files), not as root, so that PHP can keep writing to storage/.
# It never touches storage/config.php (not tracked by git) and refuses to run over local edits.
#
set -euo pipefail

cd "$(dirname "$0")/.."

BRANCH="${BRANCH:-main}"

if [ ! -d .git ]; then
    echo "This folder is not a git checkout. See docs/INSTALLATION_GE.md, section 'Update from Git'." >&2
    exit 1
fi

if [ ! -f storage/config.php ]; then
    echo "WARNING: storage/config.php is missing - the site is not installed in this folder." >&2
fi

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "Tracked files were modified on the server; refusing to update so nothing is overwritten:" >&2
    git status --short --untracked-files=no >&2
    echo "Undo them with 'git checkout -- <file>' (or ask a developer), then run this script again." >&2
    exit 1
fi

OLD="$(git rev-parse HEAD)"
git fetch --quiet origin "$BRANCH"

if ! git merge --ff-only --quiet "origin/$BRANCH"; then
    echo "The local history diverged from origin/$BRANCH; nothing was changed." >&2
    exit 1
fi

NEW="$(git rev-parse HEAD)"
VERSION="$(tr -d '\r\n' < VERSION 2>/dev/null || echo unknown)"

if [ "$OLD" = "$NEW" ]; then
    echo "Already up to date. Version: $VERSION"
    exit 0
fi

echo
echo "Updated: $(git rev-parse --short "$OLD") -> $(git rev-parse --short "$NEW")   Version: $VERSION"
echo
git log --no-merges --pretty='  * %s' "$OLD..$NEW"
echo

# Fail loudly if a deployed PHP file has a syntax error (changed files only).
if command -v php >/dev/null 2>&1; then
    if ! git diff --name-only --diff-filter=AM "$OLD" "$NEW" -- '*.php' | while read -r file; do
        php -l "$file" >/dev/null || { echo "SYNTAX ERROR in $file" >&2; exit 1; }
    done; then
        echo "The new version is broken. Roll back NOW with:  git reset --hard $OLD" >&2
        exit 1
    fi
fi

echo "Done. Next steps:"
echo "  1. Reload PHP-FPM / OPcache if the old version is still shown (FastPanel: site settings -> PHP)."
echo "  2. Open /version.txt - it must show: Makhara Invoice $VERSION"
echo "  3. Read docs/UPDATE_${VERSION}_GE.md for anything to do by hand (new config options, etc.)."
echo
echo "Roll back to the previous version (code only; the database is not changed by updates):"
echo "  git reset --hard $OLD"
