# Pasa-Buy deployment runbook

This runbook is for the **first release, a portfolio demo** with simulated wallet credits and synthetic or demo accounts. A live service with real money needs a verified payment provider and a new review first.

**No host has been chosen and nothing has been deployed to the internet.** The procedures below were rehearsed on a local production-mode staging instance (see *Rehearsal evidence*).

---

## 1. Hosting requirements

| Need | Why |
|---|---|
| PHP 8.2 or 8.3 with `intl`, `mbstring`, `mysqli`, `gd`, `fileinfo`, `zlib`, `phar` | Framework, uploads (finfo), images, backups |
| MySQL 8.0+ or MariaDB 10.4+ (InnoDB) | Row locks, CHECK and UNIQUE constraints, strict mode |
| Ability to set the **document root to `public/`** | Everything else must not be web-reachable |
| SSH / CLI access to run `php spark` | Migrations, admin provisioning, backups |
| Persistent disk for `writable/` | Sessions, logs and private uploads must survive redeploys |
| HTTPS (managed certificate or Let's Encrypt) | Secure cookies and HSTS |
| Scheduled jobs (cron) and off-server backup storage | Nightly backups |

**Unsuitable hosts:** anything that serves the project root, has an ephemeral filesystem with no persistent volume, or offers no CLI.

Candidate categories for the decision (no provider or paid plan has been chosen):
* a small VPS (for example DigitalOcean, Linode or Vultr) with Nginx or Apache, PHP-FPM and MariaDB: the most control and the cheapest at a fixed monthly price;
* a PHP shared host with SSH and a configurable document root;
* a PaaS with persistent volumes and managed MySQL.

Compare current pricing when the budget and domain are known.

## 2. Server layout

```
/srv/pasabuy/
  releases/<shortsha>/     one directory per release (from build-release.sh)
    writable -> ../../shared/writable
    .env     -> ../../shared/.env        (or a copy)
  shared/
    .env                   production settings, mode 600, owned by deploy user
    writable/              cache, logs, session, uploads (owned by the PHP user)
  current -> releases/<shortsha>         web server document root = current/public
/var/backups/pasabuy/      backup:create output (also copied off-server)
```

Example Nginx server block (core lines only):

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;
    root /srv/pasabuy/current/public;
    index index.php;
    client_max_body_size 6m;                 # 5 MB attachments + form overhead
    location / { try_files $uri $uri/ /index.php$is_args$args; }
    location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
    location ~ /\. { deny all; }
}
server { listen 80; server_name example.com; return 301 https://$host$request_uri; }
```

With Apache, point `DocumentRoot` at `.../current/public`. `public/.htaccess` does the routing; **do not** deploy the root `.htaccess` shim as a substitute.

## 3. Database users

```sql
CREATE DATABASE pasabuy_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'pasabuy_app'@'localhost'     IDENTIFIED BY '<long random>';
CREATE USER 'pasabuy_migrate'@'localhost' IDENTIFIED BY '<different long random>';
GRANT SELECT, INSERT, UPDATE, DELETE ON pasabuy_prod.* TO 'pasabuy_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES,
      LOCK TABLES, SHOW VIEW, TRIGGER ON pasabuy_prod.* TO 'pasabuy_migrate'@'localhost';
```

* `.env` holds **only** the app user (see `deploy/env.production.example`).
* The migrator's credentials are supplied only on the command line for migrations and backups, from an operator-only file such as `/root/pasabuy-migrator.env`:
  ```bash
  env $(cat /root/pasabuy-migrator.env | xargs) php spark migrate --all
  ```
  When `database_migrate_username` is set for a CLI process, that process connects as the migrator. Overriding `database.default.*` through the environment does **not** work when `.env` already sets those keys.

## 4. First deployment

```bash
# on a build machine with the repository
scripts/build-release.sh <commit> build/releases      # -> pasabuy-<sha>.tar.gz + .sha256

# on the server
sha256sum -c pasabuy-<sha>.tar.gz.sha256
tar -xzf pasabuy-<sha>.tar.gz -C /srv/pasabuy/releases && mv /srv/pasabuy/releases/pasabuy-<sha> /srv/pasabuy/releases/<sha>
cd /srv/pasabuy/releases/<sha>
rm -rf writable && ln -s ../../shared/writable writable      # first time: copy the skeleton into shared/ first
ln -s ../../shared/.env .env
chown -R www-data: /srv/pasabuy/shared/writable && chmod 600 /srv/pasabuy/shared/.env

env $(cat /root/pasabuy-migrator.env | xargs) php spark migrate --all
DEMO_SEED_PASSWORD='<demo password>' php spark db:seed DemoSeeder   # demo instance only
php spark user:role <your-email> admin                               # after registering, if not using demo admin

ln -sfn /srv/pasabuy/releases/<sha> /srv/pasabuy/current && systemctl reload php8.3-fpm
php scripts/smoke-test.php https://example.com/ <user-email> <password> <admin-email> <admin-password>
```

**Do not run `php spark optimize`.** It switches on config caching by editing `app/Config/Optimize.php`, after which `.env` changes are silently ignored until the cache is cleared. The rehearsal caught this.

## 5. Routine release

1. CI is green for the exact commit; `scripts/build-release.sh <commit>`.
2. **Backup:** `env $(cat /root/pasabuy-migrator.env | xargs) php spark backup:create /var/backups/pasabuy --keep 14`, then copy it off-server.
3. Extract and link the release (as above). The old release stays untouched.
4. Run migrations as the migrator (if any are new).
5. Switch `current` to the new release and reload PHP-FPM.
6. Run `scripts/smoke-test.php`. Any FAIL means roll back (section 6).

## 6. Rollback

* **Code-only release** (no new migrations): point `current` back at the previous release directory and reload. Rehearsed; see the evidence below.
* **Release with migrations:** either
  * `php spark migrate:rollback` as the migrator, **only** if the new migration's `down()` is lossless, then switch code back; or
  * restore the pre-release backup (section 7), then switch code back.

  Note: the 2026-11-22 hardening migration's `down()` restores the old structure but cannot recover the legacy `''` payment types or old `tax` values. To return to the pre-hardening state, use a backup.

  The November 23 migrations are also **not lossless**: wallet top-ups and moderation records are dropped, chat deletion preferences disappear, suspension state is removed, and per-delivery ratings collapse to one rating per pair. Use a pre-release backup and its matching release for operational rollback, not `migrate:rollback`.

  Stop writes and workers before backup/switch/restore so the SQL snapshot and private files describe the same point in time. Keep a separate backup of the failed release before rollback; restoring the pre-release snapshot loses later writes. Obtain an explicit data-recovery decision before doing this on a live service.

## 7. Backups and restore

```bash
# nightly cron (as the deploy user)
15 2 * * * cd /srv/pasabuy/current && env $(cat /root/pasabuy-migrator.env | xargs) php spark backup:create /var/backups/pasabuy --keep 14 && rclone copy /var/backups/pasabuy remote:pasabuy-backups
```

Each backup directory contains `database.sql.gz` (consistent `--single-transaction` dump), `uploads.tar.gz` (private uploads), `manifest.json` (commit, migration version, row counts) and `SHA256SUMS`.

```bash
env $(cat /root/pasabuy-migrator.env | xargs) php spark backup:restore /var/backups/pasabuy/pasabuy-pasabuy_prod-YYYYMMDD-HHMMSS --confirm pasabuy_prod
```

Restore verifies the checksums first, refuses to run unless `--confirm` names the target database, moves the current uploads aside (it never deletes them), and checks the row counts against the manifest. **Rehearse a restore at least once per quarter on staging.**

For a cross-schema rollback, restore into an empty, explicitly verified target database and switch to the matching archived code/configuration. A SQL dump does not remove tables introduced after that dump; importing over a newer schema can leave incompatible tables behind. From release `ec10f9a`'s successor on, `backup:restore` refuses a target that contains tables the backup lacks, and checks afterwards that the database holds exactly the backup's tables (older releases' tools only compared row counts, so they reported success with the newer tables still present). Preserve the old database first, retain/reapply the intended grants, and keep the site offline until verification passes. Use the backup/restore tool from the corresponding release for older manifests: their verification lists did not include newer tables. New backups verify all 15 current tables, including the migration ledger, top-ups, blocks, reports and chat deletions. Compare actual records and private-file hashes as well as counts during a rehearsal.

## 8. Security configuration checklist

- [ ] Document root is `public/`; `scripts/smoke-test.php` reports every sensitive path as ≥ 400
- [ ] `CI_ENVIRONMENT = production` (no debug toolbar, generic error pages)
- [ ] `app.baseURL` is https and `app.forceGlobalSecureRequests = true`
- [ ] `cookie.secure = true`; HSTS present on HTTPS responses (the smoke test checks this)
- [ ] `app.proxyIPs` set **only** if behind a proxy you control
- [ ] App DB user has DML only; migrator credentials are not in `.env`
- [ ] `writable/` is writable by PHP and nothing under it is web-reachable
- [ ] Upload size limits: PHP `upload_max_filesize ≥ 5M`, `post_max_size ≥ 6M`, web server body limit ≥ 6 MB
- [ ] Off-server backup copy configured, and a restore rehearsed
- [ ] `pasabuy.demoMode = true` (no real-money flow exists)
- [ ] Consent confirmed for `public/img/team/*` photos

## 9. Monitoring and operation

* Point an uptime monitor at `GET /health`. It returns 200 only when the database schema and storage are usable, otherwise 503.
* Logs: `shared/writable/logs/log-YYYY-MM-DD.log` (threshold 4 = errors and above). Watch for `Something went wrong` entries logged by `BaseController::attempt`.
* Wallet integrity: the admin dashboard shows the *Fee Reconciliation* and *Wallet check* cards. Anything other than *Balanced* or *all balances match* needs investigating before more activity.
* Dependencies: CI runs `composer audit` on every push. Apply CodeIgniter patch releases via `composer update codeigniter4/framework`, then the full suite, then a staging rehearsal.

## 10. Rehearsal evidence (2026-11-22, local production-mode staging)

Environment: Windows 11, PHP 8.2.12, MariaDB 10.4.32 (XAMPP), PHP built-in server with `public/` as the document root. This was **HTTP only**: there was no TLS, so the HTTPS and HSTS checks are skipped by the smoke test and must be confirmed on the real host.

| Step | Result |
|---|---|
| Build release `fbc31da171`, then `1129c1cfa6` from exact commits | Archives built; `composer audit` found no advisories; no dev packages, tests or legacy uploads included |
| Fresh install: migrate as the app user | Refused (`CREATE command denied`), as intended |
| Migrate as the migrator | 2 migrations applied |
| Seed in production mode without `DEMO_SEED_PASSWORD` | Refused, as intended |
| `scripts/smoke-test.php` (user + admin) | 51/51 checks passed |
| `forceGlobalSecureRequests` with an HTTPS baseURL | HTTP → 307 to https, including with a spoofed `X-Forwarded-Proto` from an untrusted IP |
| Upload a real PNG / a PHP file renamed `.png` | Stored privately / rejected ("content does not match") |
| Avatar URL as guest / signed in / path traversal | 302 to login / 200 `image/png` with nosniff and sandbox CSP / 404 |
| `backup:create` → drop the database and delete uploads → `backup:restore` | Checksums verified; row counts matched the manifest; avatar served again; smoke test passed |
| Restore without `--confirm`; restore of a tampered backup | Refused; "Checksum mismatch" |
| `/health` against an empty database | 503 `degraded` (fixed during the rehearsal; it previously returned 200) |
| Upgrade `fbc31da171` → `1129c1cfa6` (backup, migrate, switch) | Smoke test passed |
| Rollback to `fbc31da171` | Smoke test passed |
