#!/usr/bin/env bash
# Builds an upload-ready package for InfinityFree (no SSH, no CLI on the host).
#
#   deploy/infinityfree/build-package.sh <release-archive.tar.gz> <domain> <db-host> <db-name> <db-user> <out-dir>
#
# Output (<out-dir>/infinityfree-<sha>/):
#   htdocs/            contents of public/ (index.php points at pasabuy/)
#   pasabuy/           app, vendor, writable, .env; goes INSIDE the server's htdocs,
#                      blocked by its own .htaccess. InfinityFree confines PHP to the
#                      site's htdocs (open_basedir), so it can't sit next to it.
#                      Verified live 2026-11-24: /pasabuy/* -> 403, .env -> no response.
#   pasabuy-database.sql   schema (all migrations) + synthetic demo data, for phpMyAdmin
#   DEMO-PASSWORD.txt  random password of the demo accounts
#
# The database password is NOT in the package: .env contains a placeholder
# that the owner replaces in the host's file manager. Provider keys (TalkJS,
# Xendit test mode) are copied from the local .env; the package is written
# outside the repository and must never be committed or shared.
#
# Needs: local XAMPP MySQL (root, no password) to build the SQL; PHP; zip.
set -euo pipefail

ARCHIVE="${1:?archive}"; DOMAIN="${2:?domain}"; DB_HOST="${3:?db host}"; DB_NAME="${4:?db name}"; DB_USER="${5:?db user}"; OUT="${6:?out dir}"
NAME="$(basename "$ARCHIVE" .tar.gz)"; SHA="${NAME#pasabuy-}"
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
MYSQL="${MYSQL_BIN:-/c/xampp/mysql/bin}"
PKG="$OUT/infinityfree-$SHA"
WORK="$PKG/pasabuy"
TMPDB="pb_if_build_${SHA:0:7}"

(cd "$(dirname "$ARCHIVE")" && sha256sum -c "$(basename "$ARCHIVE").sha256")
rm -rf "$PKG"; mkdir -p "$PKG"
tar -xzf "$ARCHIVE" -C "$PKG"
mv "$PKG/$NAME" "$WORK"

echo "== fresh writable skeleton"
rm -rf "$WORK/writable"
mkdir -p "$WORK/writable"/{cache,logs,session,uploads,debugbar}
for d in "" cache logs session uploads debugbar; do cp "$REPO/writable/index.html" "$WORK/writable/$d/index.html" 2>/dev/null || true; done
cp "$REPO/writable/.htaccess" "$WORK/writable/.htaccess" 2>/dev/null || printf 'Require all denied\n' > "$WORK/writable/.htaccess"

echo "== database: migrate + seed a throwaway local DB, then dump"
DEMO_PW="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-14)"
"$MYSQL/mysql" -uroot -e "DROP DATABASE IF EXISTS $TMPDB; CREATE DATABASE $TMPDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
cat > "$WORK/.env" <<ENV
CI_ENVIRONMENT = production
app.baseURL = 'http://localhost/'
database.default.hostname = 127.0.0.1
database.default.database = $TMPDB
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
ENV
# Only the .env written above may choose the database: drop inherited overrides.
(cd "$WORK" && unset database_migrate_username database_migrate_password database_migrate_database \
    && php spark migrate --all >/dev/null && DEMO_SEED_PASSWORD="$DEMO_PW" php spark db:seed DemoSeeder)
"$MYSQL/mysqldump" -uroot --single-transaction --skip-lock-tables --no-tablespaces --default-character-set=utf8mb4 --skip-comments "$TMPDB" \
    | sed -E 's/ DEFINER=`[^`]+`@`[^`]+`//g' > "$PKG/pasabuy-database.sql"
"$MYSQL/mysql" -uroot -e "DROP DATABASE $TMPDB"
printf '%s\n' "$DEMO_PW" > "$PKG/DEMO-PASSWORD.txt"
rm -rf "$WORK/writable/cache/"* "$WORK/writable/logs/"* "$WORK/writable/session/"* 2>/dev/null || true
for d in cache logs session; do cp "$REPO/writable/index.html" "$WORK/writable/$d/index.html" 2>/dev/null || true; done

echo "== web root"
mv "$WORK/public" "$PKG/htdocs"
sed -i "s#require FCPATH . '../app/Config/Paths.php';#require FCPATH . 'pasabuy/app/Config/Paths.php';#" "$PKG/htdocs/index.php"
grep -q "FCPATH . 'pasabuy/app/Config/Paths.php'" "$PKG/htdocs/index.php"
# The app folder lives inside the web root on this host: refuse every request for it.
cat > "$WORK/.htaccess" <<'HTA'
# Pasa-Buy application code, settings and private files: never served.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
HTA

echo "== production .env (database password left as a placeholder)"
keys="$(grep -E '^\s*(talkjs\.appId|talkjs\.secretKey|pasabuy\.talkjsEnabled|xendit\.secretKey|xendit\.callbackToken)\s*=' "$REPO/.env" | tr -d '\r' || true)"
cat > "$WORK/.env" <<ENV
CI_ENVIRONMENT = production
app.baseURL = 'https://${DOMAIN}/'
app.forceGlobalSecureRequests = true
encryption.key = hex2bin:$(openssl rand -hex 32)

database.default.hostname = ${DB_HOST}
database.default.database = ${DB_NAME}
database.default.username = ${DB_USER}
database.default.password = PUT-YOUR-MYSQL-PASSWORD-HERE
database.default.DBDriver = MySQLi
database.default.port = 3306

cookie.secure = true
cookie.httponly = true
cookie.samesite = Lax
session.cookieName = 'pasabuy_session'
session.expiration = 7200
session.regenerateDestroy = true
logger.threshold = 4

pasabuy.demoMode = true
pasabuy.cspMode = enforce

# Provider keys copied from the build machine's .env (test mode only).
${keys}
ENV

cat > "$PKG/README-UPLOAD.txt" <<TXT
Pasa-Buy ${SHA} for ${DOMAIN}

1. With FileZilla (FTP details from the InfinityFree control panel):
   - upload the CONTENTS of htdocs/ into the server's htdocs/ folder (replace files);
   - upload the pasabuy/ folder INTO the server's htdocs/ (next to index.php).
   Use the site's own folder (e.g. /<domain>/htdocs), turn on Server > Force
   showing hidden files, set Settings > Transfers > File exists action >
   Uploads: Overwrite, and Maximum simultaneous transfers: 1.
2. In the host's file manager, edit htdocs/pasabuy/.env and replace
   PUT-YOUR-MYSQL-PASSWORD-HERE with your MySQL password.
3. phpMyAdmin (control panel) -> your database -> Import -> pasabuy-database.sql.
4. Open https://${DOMAIN}/ . Demo accounts: admin@, requester@, runner@,
   runner2@, requester2@demo.pasabuy.test ; password in DEMO-PASSWORD.txt.
This folder contains secrets: keep it private, do not commit or share it.
TXT
echo "== package ready: $PKG"
