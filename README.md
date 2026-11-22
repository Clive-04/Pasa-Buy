# Pasa-Buy

Pasa-Buy is a community errand marketplace. A **requester** posts an item they need from a nearby store, a **runner** accepts it, buys it and delivers it, and the two coordinate through in-app messages. Payment is by wallet or cash on delivery. **Admins** monitor requests and platform fees.

> **Status: portfolio demo.** Wallet balances are *simulated demo credits*. No real money, GCash, Maya or bank transfer is processed, and user-entered payment references are never treated as proof of funds. The app shows a demo banner on every page.

## Features

| Area | What it does |
|---|---|
| Accounts | Registration with validation, login with throttling, role-based home (user/admin), POST-only logout |
| Requests | Post (with live price summary and local drafts), browse/filter/paginate, accept, *On the way*, deliver, cancel |
| Payments | Wallet payment from requester to runner, or cash on delivery; **10% platform fee on the service fee, paid by the runner** |
| Wallet | Demo-credit top-ups (capped, idempotent), full ledger with running balance |
| Messages | Conversations per user/request, private attachments (JPG/PNG/WEBP/PDF/DOCX ≤ 5 MB) |
| Notifications | Request events, unread badges refreshed in place |
| Profiles | Photo (private storage), ratings from completed errands, website rating, referrals (saved, no email sent) |
| Admin | Ongoing/delivered lists, recorded platform fees, fee reconciliation, wallet-vs-ledger check, cancel unpaid requests |

### Money rules

```
Requester pays   = item estimated price + service fee
Platform fee     = 10% of service fee (rounded half-up to the centavo)
Runner receives  = requester pays − platform fee
```

* **Wallet:** charged when the requester pays; requester debit, runner credit and fee debit happen in one transaction.
* **Cash on delivery:** the requester pays the runner in cash; the fee is debited from the runner's wallet when they confirm delivery. Runners need enough balance for the fee to accept a COD request.

All amounts are calculated in integer centavos (`App\Libraries\Money`, `App\Libraries\FeePolicy`).

## Technology (verified in this repository)

* PHP ≥ 8.2, **CodeIgniter 4.7.6** (Composer, locked)
* MySQL 8 / MariaDB 10.4+ via MySQLi, strict SQL mode
* Server-rendered PHP views, plain CSS and JavaScript (no build step)
* PHPUnit 10 for unit, database, concurrency and HTTP tests

## Project layout

```
app/
  Config/            Routes, filters (auth/admin/CSRF), PasaBuy.php settings
  Controllers/       Thin HTTP layer
  Services/          RequestLifecycle, WalletService — all state changes, transactional
  Libraries/         Money, FeePolicy, UploadStorage, BackupTools
  Database/          Migrations (baseline + hardening) and DemoSeeder
  Commands/          spark user:role, uploads:import-legacy, backup:create, backup:restore
  Views/             Pages and shared includes
public/              Web document root (index.php, css, js, img) — the ONLY public folder
writable/            Logs, sessions, cache, private uploads (never web-accessible)
tests/               unit/, database/ (incl. multi-process concurrency), feature/
deploy/              Staging/production .env templates
scripts/             smoke-test.php, build-release.sh
```

## Local setup (XAMPP on Windows, or any PHP + MySQL)

1. **Requirements:** PHP 8.2+ with `intl`, `mbstring`, `mysqli`, `gd`, `fileinfo`; Composer 2; MySQL 8 or MariaDB 10.4+.
2. **Install:**
   ```bash
   composer install
   cp .env.example .env        # then adjust database credentials if needed
   ```
3. **Database:**
   ```sql
   CREATE DATABASE pasa_buy_db  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   CREATE DATABASE pasa_buy_test CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
   ```bash
   php spark migrate --all
   php spark db:seed DemoSeeder      # synthetic demo accounts and requests
   ```
4. **Run:**
   * XAMPP: open `http://localhost/Pasa-Buy-Project/`. The root `.htaccess` forwards everything into `public/`. This is a local shortcut only.
   * Or the built-in server: `php spark serve`, then open `http://localhost:8080/` and set `app.baseURL` accordingly.

### Demo accounts (local seed)

Password: `DemoPass#2026` locally, or `DEMO_SEED_PASSWORD` if you set it.

| Email | Role |
|---|---|
| `requester@demo.pasabuy.test` | Requester with demo credits and requests in every state |
| `runner@demo.pasabuy.test` | Runner with a completed wallet delivery and an active COD job |
| `runner2@demo.pasabuy.test` | Runner with a completed COD delivery |
| `requester2@demo.pasabuy.test` | Second requester |
| `admin@demo.pasabuy.test` | Administrator |

Grant or revoke admin for any existing account: `php spark user:role someone@example.com admin`. Public registration always creates ordinary users.

### Upgrading a pre-2026-11 database

The baseline migration leaves existing tables untouched; the hardening migration converts them (it was rehearsed on a copy of the May 2026 data). **Back up first**, then:

```bash
php spark backup:create ../backups
php spark migrate --all
php spark uploads:import-legacy   # copies old root uploads/ into writable/uploads (originals kept)
```

## Tests

```bash
vendor/bin/phpunit --no-coverage
```

* Uses the `tests` connection (`pasa_buy_test` by default, configurable with `database.tests.*`). **That database is wiped**; the suite refuses to run unless its name contains `test`.
* Includes multi-process concurrency tests (simultaneous accepts, payments, COD confirmations and top-ups), so it needs a real MySQL/MariaDB. SQLite is not supported.
* CI (`.github/workflows/ci.yml`) runs composer validate/audit, a PHP lint and the suite on PHP 8.2/8.3 against MySQL 8.0 and MariaDB 10.6 for every push to `main` and every pull request.

## Security model (summary)

* `public/` is the only web root; config, code, vendor, logs, sessions and uploads are outside it.
* Session-based CSRF protection on every POST; state changes are POST-only.
* `auth` route filter for all signed-in pages; `admin` filter re-checks the role in the database.
* Login throttling, session-ID regeneration, generic login errors, bcrypt hashes.
* Uploads are checked by extension and detected content type, stored with random names in `writable/uploads`, and served only to authorized users with `nosniff` and a sandbox CSP.
* Every view escapes output; exact delivery address and phone are shown only to the request's participants.
* Database constraints back the application rules: no negative balances, one assignment per request, and one ledger entry per request per type.

## Deployment

See **[DEPLOYMENT.md](DEPLOYMENT.md)** for hosting requirements, the release, backup, restore and rollback procedures, and the staging rehearsal results.

## Known limitations

* Demo mode only; there is no verified payment provider. `pasabuy.demoMode = false` disables top-ups entirely.
* There are no refunds. Paid or delivered requests cannot be cancelled.
* Badges refresh by polling every 30 s (no WebSockets); messages refresh on navigation.
* No email delivery, so referrals are recorded only.
* A Content-Security-Policy for pages is not yet enabled, because several views still contain inline scripts.
* Legacy user uploads in the project-root `uploads/` folder are still tracked in git. Release builds exclude them and they are never served, but removing them from the repository (or its history) needs the owner's decision.
* `public/img/team/` photos are deployed and shown on the About page; confirm consent before a public launch.

## License

MIT (see `LICENSE`). CodeIgniter is © CodeIgniter Foundation, MIT licensed.
