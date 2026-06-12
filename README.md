# Estate BOS — Palm Oil Estate Business Operating System

Native PHP 8 + MySQL/MariaDB management system for a 1,000+ acre palm oil
estate, built to run on **Hostinger shared hosting** (no Laravel, no Node.js
runtime, no build step). Front-end uses Bootstrap 5 via CDN.

This repository currently implements **Phase 1 — System Foundation**:

- Session-based login / logout with `password_hash()` + `password_verify()`
- Role-based permission checks (`can()` / `require_permission()`)
- CSRF protection on every POST form
- XSS-safe output via `e()` (`htmlspecialchars`)
- Activity / audit logging
- Admin dashboard shell with responsive sidebar
- Users module (list, create, edit) — searchable + paginated
- Roles module with a permission matrix

Later phases (estate setup, harvest, workers, inventory, fuel, mill delivery,
costing, compliance, GIS, AI assistant) follow the development plan in the
blueprint.

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
import the SQL in order:

```bash
mysql -u <user> -p <database> < database/schema.sql
mysql -u <user> -p <database> < database/seed.sql
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
