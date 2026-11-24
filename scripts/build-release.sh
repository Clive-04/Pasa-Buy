#!/usr/bin/env bash
#
# Builds a deployable release of an exact git commit.
#
#   scripts/build-release.sh [commit=HEAD] [output-dir=build/releases]
#
# Output: <output-dir>/pasabuy-<shortsha>.tar.gz (+ .sha256)
#
# The archive contains only committed files at that commit, production
# Composer dependencies from composer.lock, and a RELEASE file with the
# full commit hash. It deliberately excludes: tests, CI config, dev tools,
# local .env files and the legacy project-root uploads/ (personal files).
set -euo pipefail

COMMIT="${1:-HEAD}"
OUT="${2:-build/releases}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

cd "$ROOT"
SHA="$(git rev-parse --verify "${COMMIT}^{commit}")"
SHORT="$(git rev-parse --short=10 "$SHA")"

if ! git diff --quiet "$SHA" -- 2>/dev/null && [ "$COMMIT" = "HEAD" ]; then
    echo "warning: working tree differs from $SHORT; the release uses the COMMITTED state only." >&2
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
STAGE="$WORK/pasabuy-$SHORT"
mkdir -p "$STAGE"

git archive "$SHA" | tar -x -C "$STAGE"

(
    cd "$STAGE"
    composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --classmap-authoritative
    composer audit --no-dev --no-interaction --locked

    rm -rf tests .github builds uploads phpunit.xml.dist .gitattributes
    # writable/ must exist but ship empty (only its index.html guards).
    find writable -type f ! -name 'index.html' ! -name '.htaccess' -delete
    mkdir -p writable/uploads/profile_photos writable/uploads/message_attachments

    printf '%s\n' "$SHA" > RELEASE
)

mkdir -p "$ROOT/$OUT"
ARCHIVE="$ROOT/$OUT/pasabuy-$SHORT.tar.gz"
tar -czf "$ARCHIVE" -C "$WORK" "pasabuy-$SHORT"
( cd "$ROOT/$OUT" && sha256sum "pasabuy-$SHORT.tar.gz" > "pasabuy-$SHORT.tar.gz.sha256" )

echo "Release: $ARCHIVE"
cat "$ARCHIVE.sha256"
