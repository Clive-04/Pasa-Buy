#!/usr/bin/env bash
# Installs one release archive on the server and switches to it.
#
#   sudo bash release.sh /tmp/pasabuy-<sha>.tar.gz [--seed-demo]
#
# Verifies the checksum, backs up first (if a release is live), extracts to
# /srv/pasabuy/releases/<sha>, links shared .env and writable, runs migrations
# as the migration user, optionally seeds demo data, then switches `current`
# and reloads PHP. The previous release stays on disk for rollback.
set -euo pipefail

ARCHIVE="${1:?usage: release.sh <archive.tar.gz> [--seed-demo]}"
SEED="${2:-}"
APP=/srv/pasabuy
NAME="$(basename "$ARCHIVE" .tar.gz)"     # pasabuy-<sha>
SHA="${NAME#pasabuy-}"
TARGET="$APP/releases/$SHA"

cd "$(dirname "$ARCHIVE")"
sha256sum -c "$(basename "$ARCHIVE").sha256"

if [ -L "$APP/current" ] && [ -f "$APP/current/spark" ]; then
    echo "== backup before switching"
    (cd "$APP/current" && env $(cat /root/pasabuy-migrator.env | xargs) MYSQLDUMP=/usr/bin/mysqldump php spark backup:create /var/backups/pasabuy --keep 14)
fi

echo "== extract $SHA"
rm -rf "$TARGET" "$APP/releases/$NAME"
tar -xzf "$ARCHIVE" -C "$APP/releases"
mv "$APP/releases/$NAME" "$TARGET"
rm -rf "$TARGET/writable"
ln -s "$APP/shared/writable" "$TARGET/writable"
ln -sfn "$APP/shared/.env" "$TARGET/.env"
chown -R root:root "$TARGET"

echo "== migrate (migration user)"
(cd "$TARGET" && php spark db:table --show | grep -q "pasabuy_prod" && env $(cat /root/pasabuy-migrator.env | xargs) php spark migrate --all)

if [ "$SEED" = "--seed-demo" ]; then
    echo "== demo data"
    if [ ! -f /root/pasabuy-demo.pw ]; then
        umask 077; openssl rand -base64 18 | tr -d '/+=' | cut -c1-16 > /root/pasabuy-demo.pw; umask 022
    fi
    (cd "$TARGET" && env $(cat /root/pasabuy-migrator.env | xargs) DEMO_SEED_PASSWORD="$(cat /root/pasabuy-demo.pw)" php spark db:seed DemoSeeder)
    chown -R www-data:www-data "$APP/shared/writable/uploads"
fi

echo "== switch"
rmdir "$APP/current/public" "$APP/current" 2>/dev/null || true   # placeholder from provisioning
ln -sfn "$TARGET" "$APP/current"
systemctl reload php8.3-fpm
systemctl reload nginx
echo "== live: $SHA"
