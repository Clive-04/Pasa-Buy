#!/usr/bin/env bash
# One-time setup of an Oracle Cloud Always Free Ubuntu 24.04 VM for Pasa-Buy.
#
#   sudo bash provision.sh <domain> <admin-email-for-letsencrypt>
#
# Installs Nginx, PHP 8.3-FPM, MariaDB and Certbot; creates the database with a
# DML-only app user and a separate migration user (random passwords generated
# here, stored root-only, never printed); prepares /srv/pasabuy; opens 80/443 in
# the VM's own firewall (Oracle images block them even when the cloud security
# list allows them); adds swap, PHP upload limits, nightly backups and
# automatic security updates. Safe to re-run: existing secrets are kept.
set -euo pipefail

DOMAIN="${1:?usage: provision.sh <domain> <letsencrypt-email>}"
LE_EMAIL="${2:?usage: provision.sh <domain> <letsencrypt-email>}"
APP=/srv/pasabuy
DB=pasabuy_prod
export DEBIAN_FRONTEND=noninteractive

echo "== packages"
apt-get update -q
apt-get install -y -q nginx mariadb-server certbot python3-certbot-nginx unattended-upgrades \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-intl php8.3-gd php8.3-curl php8.3-xml php8.3-zip \
    iptables-persistent curl unzip

echo "== firewall: allow HTTP/HTTPS (Oracle images default to REJECT)"
for port in 80 443; do
    if ! iptables -C INPUT -p tcp -m state --state NEW --dport "$port" -j ACCEPT 2>/dev/null; then
        # insert before Oracle's final REJECT rule
        iptables -I INPUT 5 -p tcp -m state --state NEW --dport "$port" -j ACCEPT
    fi
done
netfilter-persistent save

echo "== swap (helps on the 1 GB micro shape)"
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "== PHP settings"
cat > /etc/php/8.3/fpm/conf.d/90-pasabuy.ini <<'INI'
expose_php = Off
upload_max_filesize = 6M
post_max_size = 8M
memory_limit = 256M
session.cookie_secure = 1
session.cookie_httponly = 1
INI
systemctl restart php8.3-fpm

echo "== database and users"
mkdir -p /root && chmod 700 /root
if [ ! -f /root/pasabuy-migrator.env ]; then
    APP_PW="$(openssl rand -base64 30 | tr -d '/+=' | cut -c1-32)"
    MIG_PW="$(openssl rand -base64 30 | tr -d '/+=' | cut -c1-32)"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS ${DB} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'pasabuy_app'@'localhost' IDENTIFIED BY '${APP_PW}';
CREATE USER IF NOT EXISTS 'pasabuy_migrate'@'localhost' IDENTIFIED BY '${MIG_PW}';
GRANT SELECT, INSERT, UPDATE, DELETE ON ${DB}.* TO 'pasabuy_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, LOCK TABLES, SHOW VIEW, TRIGGER ON ${DB}.* TO 'pasabuy_migrate'@'localhost';
FLUSH PRIVILEGES;
SQL
    umask 077
    printf 'database_migrate_username=pasabuy_migrate\ndatabase_migrate_password=%s\n' "$MIG_PW" > /root/pasabuy-migrator.env
    printf '%s' "$APP_PW" > /root/pasabuy-app-db.pw
    umask 022
fi

echo "== app layout"
mkdir -p "$APP/releases" "$APP/shared/writable"/{cache,logs,session,uploads,debugbar} /var/backups/pasabuy
for d in cache logs session uploads debugbar; do
    [ -f "$APP/shared/writable/$d/index.html" ] || echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > "$APP/shared/writable/$d/index.html"
done
chown -R www-data:www-data "$APP/shared/writable"
chmod 700 /var/backups/pasabuy

if [ ! -f "$APP/shared/.env" ]; then
    APP_PW="$(cat /root/pasabuy-app-db.pw)"
    ENC_KEY="hex2bin:$(openssl rand -hex 32)"
    umask 077
    cat > "$APP/shared/.env" <<ENV
CI_ENVIRONMENT = production
app.baseURL = 'https://${DOMAIN}/'
app.forceGlobalSecureRequests = true
encryption.key = ${ENC_KEY}

database.default.hostname = localhost
database.default.database = ${DB}
database.default.username = pasabuy_app
database.default.password = ${APP_PW}
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
ENV
    umask 022
    chown root:www-data "$APP/shared/.env"
    chmod 640 "$APP/shared/.env"
fi

echo "== nginx"
cat > /etc/nginx/sites-available/pasabuy <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${APP}/current/public;
    index index.php;
    client_max_body_size 8m;
    server_tokens off;

    location / { try_files \$uri \$uri/ /index.php\$is_args\$args; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\. { deny all; }
}
NGINX
ln -sfn /etc/nginx/sites-available/pasabuy /etc/nginx/sites-enabled/pasabuy
rm -f /etc/nginx/sites-enabled/default
mkdir -p "$APP/current/public" 2>/dev/null || true
nginx -t && systemctl reload nginx

echo "== HTTPS certificate (Let's Encrypt)"
if [ ! -d "/etc/letsencrypt/live/${DOMAIN}" ]; then
    certbot --nginx --non-interactive --agree-tos -m "$LE_EMAIL" -d "$DOMAIN" --redirect
fi

echo "== nightly backups (02:30, keep 14) and security updates"
cat > /etc/cron.d/pasabuy-backup <<CRON
30 2 * * * root cd ${APP}/current && env \$(cat /root/pasabuy-migrator.env | xargs) MYSQLDUMP=/usr/bin/mysqldump php spark backup:create /var/backups/pasabuy --keep 14 >> /var/log/pasabuy-backup.log 2>&1
CRON
chmod 644 /etc/cron.d/pasabuy-backup
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "== done. Next: deploy a release with release.sh"
