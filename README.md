# Estate BOS — Palm Oil Estate Business Operating System

Native PHP 8 + MySQL/MariaDB management system for a 1,000+ acre palm oil
estate, built to run on **Hostinger shared hosting** (no Laravel, no Node.js
runtime, no build step). Front-end uses Bootstrap 5 via CDN.

This repository implements the full blueprint, built in phases:

1. **System Foundation** — session auth, roles & permission matrix, audit log, dashboard
2. **Estate Master** — estates → divisions → blocks → plots, GPS, status
3. **Harvest & FFB** — daily entry, team & photos, supervisor approval, daily/monthly reports
4. **Workers & Attendance** — profiles, documents, attendance register, permit/contract expiry alerts
5. **Field Tasks** — assignment, start/complete/approve workflow, proof photos, completion report
6. **Fertilizer & Chemical** — product masters, stock movements, applications & spraying (PPE/weather), reports
7. **Inventory, Asset & Fuel** — item master & valuation, vehicle/machinery maintenance & reminders, fuel control with abnormal-usage alerts
8. **Mill Delivery** — trips, weighbridge tickets, gross/tare/net, price/OER, harvest reconciliation
9. **Costing & Reports** — costing dashboard (cost/tonne, cost/acre, profit by block/division), CSV & print-to-PDF export
10. **AI Assistant** — rule-based query assistant and monthly insight generation, with the blueprint's AI safety rules

Cross-cutting: every POST form carries a CSRF token, all output is escaped with
`e()`, queries use PDO prepared statements, and each module is permission-gated.

## Tech stack

| Layer        | Choice                                   |
|--------------|------------------------------------------|
| Backend      | Native PHP 8.x (modular MVC-lite)        |
| Database     | MySQL / MariaDB (PDO prepared statements)|
| Frontend     | HTML5, Bootstrap 5, Bootstrap Icons, JS  |
| Auth         | PHP sessions                             |
| Hosting      | Hostinger shared hosting                 |

## Folder structure

```
estate-bos/
├── index.php            # routes to dashboard or login
├── login.php / logout.php
├── dashboard.php
├── config/              # app.php, db.php, permissions.php  (web-denied)
├── inc/                 # auth, functions, csrf, audit, header/sidebar/footer
├── assets/              # css, js, img
├── uploads/             # user uploads (php execution disabled)
├── modules/             # users, roles, ... (one folder per module)
├── reports/             # report endpoints (later phases)
├── cron/                # scheduled summaries (later phases)
└── database/            # schema.sql + seed.sql
```

## Installation

### 1. Create the database

In hPanel → Databases → MySQL Databases, create a database and user, then
import the SQL **in order** (base first, then each phase's schema, then the seeds):

```bash
# Phase 1 base (required first)
mysql -u <user> -p <db> < database/schema.sql
mysql -u <user> -p <db> < database/seed.sql

# Phase 2–10 schema
for n in 2 3 4 5 6 7 8 9 10; do
  mysql -u <user> -p <db> < database/schema_phase${n}.sql
done

# Phase 2–10 seeds (permissions + sample data)
for n in 2 3 4 5 6 7 8 9 10; do
  mysql -u <user> -p <db> < database/seed_phase${n}.sql
done
```

A scheduled costing snapshot can be wired to Hostinger cron:

```
php /home/USER/public_html/estate-bos/cron/monthly_summary.php
```

### 2. Configure the connection

Edit `config/db.php` and set `$DB_HOST`, `$DB_NAME`, `$DB_USER`, `$DB_PASS`.

If the app is installed in a subfolder (e.g. `/public_html/estate-bos/`),
set `BASE_URL` in `config/app.php` to match (e.g. `'/estate-bos'`). Leave it
as `''` when installed at the domain root.

### 3. Sign in

Open the site in a browser. Default Super Admin credentials (from `seed.sql`):

```
Email:    admin@estate-bos.local
Password: Admin@123
```

**Change this password immediately** after first login (Users → edit your
account).

## Security notes

- `config/` and `inc/` ship with `.htaccess` rules denying direct web access.
- `uploads/` disables PHP execution.
- All queries use PDO prepared statements.
- All output is escaped with `e()`.
- Sessions use HttpOnly cookies, regenerate on login, and time out after
  30 minutes of inactivity (configurable in `config/app.php`).

## Local development

You can run it with PHP's built-in server for quick testing:

```bash
php -S localhost:8000
```
