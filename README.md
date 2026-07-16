# BT Yönetim Sistemi (ITMS) / Betech

[Türkçe README](README.tr.md)

**BT Yönetim Sistemi (ITMS)** is a lightweight, open-source, self-hosted platform for IT inventory, help desk, network/IP management, and operational workflows inside your own infrastructure. Unlike multi-tenant SaaS products, ITMS runs as a **standalone application** on your servers: one organization, one database, full control over data, authentication, and network boundaries.

Built with **PHP 8.1+**, **Slim 4**, **Medoo**, **MySQL**, **Alpine.js**, and **Tailwind CSS**. Spreadsheet import/export uses **PhpSpreadsheet**. Optional integrations include LDAP, Google/Microsoft SSO, SMTP, IMAP inbox fetching, Cloudflare Turnstile, Telegram alerts, and Cloudflare R2 backups.

---

## Table of contents

1. [Key features](#key-features)
2. [Roles and navigation](#roles-and-navigation)
3. [Architecture](#architecture)
4. [System requirements](#system-requirements)
5. [Installation](#installation)
6. [Upgrading](#upgrading)
7. [CLI tools](#cli-tools)
8. [Localization](#localization)
9. [Project structure](#project-structure)
10. [Security notes](#security-notes)
11. [License](#license)

---

## Key features

### Polymorphic inventory (Varlık Yönetimi)

- Assets are stored in **per-type tables** (`assets_{slug}`) driven by configurable **asset types** (e.g. Switchler, laptops, printers).
- Core fields include tag, name, serial, status, location, assignment, and type-specific columns.
- **Auto-generated inventory tags** in sequential `ENV-####` format (e.g. `ENV-0001`) on create.
- Custom fields and hardware components are managed per asset type.
- Standalone **Add / Edit** inventory pages (`/inventory/add`, `/inventory/edit`) with type-aware forms.
- CSV/Excel **import** and **export** for inventory records.
- QR labels and a public mobile asset page at `/assets/view/{id}`.
- Assignment workflows: assign, return to storage, direct transfer, offboarding reclaim, zimmet tutanak (Quill HTML templates).

### Help desk (Yardım Masası)

- Ticket lifecycle for IT support (create, status, comments, categories).
- End-user **portal**: my tickets, published knowledge base, my assigned assets.
- Optional **IMAP inbox fetching** (`php cli.php mail:fetch_inbox`) to open tickets from email.
- SMTP notifications for ticket events (configured under Settings).

### Knowledge base & quality documents

- Internal knowledge articles (draft/published) for operators and end users.
- Quality / IT policy document library with upload and download.

### Consumables & software licenses (SAM)

- Consumables stock with checkout/restock.
- Licenses with seat capacity; seats map to a device **or** a person.
- Asset detail can list licenses assigned to that hardware.

### Network & IP management (Ağ & IP Yönetimi)

- IP networks (subnets/VLANs) with utilization meters.
- Per-network IP address grid: status filters, bulk edit, notes, hostname, MAC, asset tag.
- ICMP **ping heartbeat** status and **rogue device** flags on IPs.
- **Import from Excel/CSV** (networks or addresses) and **Export to Excel/CSV**:
  - Networks list: `GET /api/ip-networks/export`
  - Single network addresses: `GET /api/ip-networks/{id}/export`

### Switch port management (Switch Port Yönetimi)

- Switch directory built from inventory switch asset types (including legacy table discovery).
- Visual **port matrix** per switch.
- Port configuration stores a **free-text description** of what is connected (no inventory device linking required).
- Routes: `/network/switch-ports`, `/network/port-config`, `/switch-ports.php`.

### Maintenance tracking

- Maintenance / repair logs API and schema for assets under repair (provider, cost, dates, status).

### Personnel & system users

- **System users** (`admin`): operators of the ITMS dashboard.
- **Personnel** (`user` / end users): directory people used for zimmet; self-service portal only.
- LDAP / Google directory **sync** into the local personnel table (paged).
- Manual personnel creation when directory search has no match.

### Authentication

| Provider | Method |
|----------|--------|
| Local database | Email and password |
| LDAP / Active Directory | Direct user bind |
| Google Workspace | OAuth 2.0 |
| Microsoft 365 | Azure AD OAuth 2.0 + Graph |

Optional **Cloudflare Turnstile** on the login page.

### Analytics, reports, audit & backups

- Dashboard overview cards and activity.
- Help desk / operational reports.
- System audit logs.
- Database backups (local + optional **Cloudflare R2** remote storage).
- CLI: daily summary mail, health scan (Telegram), on-demand backup.

### Automated database setup

On first request, `public/index.php` runs `DatabaseInitializer`, which applies `database/schema.sql`, incremental migrations under `database/migrations/`, and `database/seeds.sql`. No separate installer wizard.

---

## Roles and navigation

ITMS uses two session roles (legacy names such as `super_admin` / `technician` / `end_user` normalize to these):

| Role | UI | Access |
|------|-----|--------|
| **Admin** (`admin`) | Operations dashboard | Full operational UI: inventory, help desk, IPAM, switch ports, licenses, settings, reports, backups |
| **User** (`user`) | End-user portal | Own assets, own tickets, published knowledge base |

Typical admin sidebar sections:

- **Operations** — Help desk, knowledge base, reports, quality documents  
- **Asset management** — Per-type inventory lists, consumables, licenses, personnel  
- **Infrastructure** — Ağ & IP Yönetimi, Switch Port Yönetimi  
- **System** — Settings / backups, system users, audit logs, asset type configuration  

Default seeded local admin (change immediately in production):

| Field | Value |
|-------|-------|
| Email | `admin@betech.local` |
| Password | `admin123` |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Your network (on-premise or private cloud)                 │
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐  │
│  │ Nginx/Apache │───▶│  PHP-FPM     │───▶│  MySQL       │  │
│  │  → public/   │    │  Slim 4 app  │    │              │  │
│  └──────────────┘    └──────────────┘    └──────────────┘  │
│                              │                              │
│                              ├── LDAP (optional)            │
│                              ├── Google / Microsoft OAuth   │
│                              ├── SMTP / IMAP (optional)     │
│                              └── R2 / Telegram (optional)   │
└─────────────────────────────────────────────────────────────┘
```

All application state lives in your MySQL instance. ITMS does not require a vendor-hosted backend.

---

## System requirements

| Component | Requirement |
|-----------|-------------|
| **PHP** | 8.1+ (`ext-json`, `ext-pdo_mysql`, `ext-curl`; optional: `ext-ldap`, `ext-imap`, `ext-gd`/`ext-zip` for spreadsheets) |
| **Database** | MySQL 5.7.8+ or MariaDB 10.2+ |
| **Composer** | 2.x |
| **Web server** | Apache 2.4+ (`mod_rewrite`) or Nginx 1.18+ |
| **Node (dev)** | Optional, for Tailwind CSS builds (`npm run build`) |
| **OS** | Linux recommended (macOS fine for development) |

---

## Installation

### 1. Clone

```bash
git clone https://github.com/ardacetin/betech.git
cd betech
```

### 2. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configure environment

```bash
cp .env.example .env
```

Minimum `.env` values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://itms.yourcompany.local

DB_TYPE=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=betech
DB_USERNAME=betech
DB_PASSWORD=your_secure_password
DB_CHARSET=utf8mb4
```

Create the database:

```sql
CREATE DATABASE betech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'betech'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON betech.* TO 'betech'@'localhost';
FLUSH PRIVILEGES;
```

Optional `.env` areas (also partly configurable in Admin UI): SMTP, IMAP inbox, Telegram health alerts, Turnstile, Cloudflare R2. See `.env.example`.

### 4. Web server document root

Point the vhost **document root at `public/`** (not the repo root).

#### Nginx (sketch)

```nginx
server {
    listen 80;
    server_name itms.yourcompany.local;
    root /var/www/betech/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. { deny all; }
}
```

#### Apache

Enable `mod_rewrite`. `public/.htaccess` routes requests to `index.php`.

### 5. First boot

Open the application URL. `DatabaseInitializer` creates/upgrades schema and seeds defaults. On failure, check web logs for `[Betech]` messages.

### 6. Sign in and harden

1. Sign in with the seeded admin account and change the password.
2. Configure auth, SMTP, and directory sync under **Settings**.
3. OAuth redirect URIs (when enabling SSO), matching `APP_URL`:
   - `{APP_URL}/auth/callback/google`
   - `{APP_URL}/auth/callback/microsoft`

---

## Upgrading

On the production host:

```bash
./deploy.sh
```

This pulls `origin/main`, runs `composer install --no-dev --optimize-autoloader`, and clears `var/cache/` if present. Then open the site once so pending migrations apply.

---

## CLI tools

Run from the project root:

```bash
php cli.php make:admin <username>     # Promote LDAP personnel to admin after first login
php cli.php mail:fetch_inbox          # Pull support inbox → tickets (needs ext-imap + config)
php cli.php notify:daily_summary      # Email daily operational summary
php cli.php notify:health_scan        # Health scan alerts (optional Telegram)
php cli.php automation:run            # Evaluate scheduled automation rules (license/warranty/stock)
php cli.php backup:database           # Database backup (optional R2 upload)
```

Schedule IMAP fetch and health scan via cron as needed (every 5–15 minutes for inbox). Run `automation:run` daily (e.g. once in the morning) so license, warranty, and low-stock rules can email. Critical ticket rules fire immediately when a ticket is created.

---

## Localization

| Locale | Code | Notes |
|--------|------|--------|
| Turkish | `tr` | Default UI language |
| English | `en` | Alternate UI language |

Files: `lang/tr.php`, `lang/en.php`. Switch with `?lang=tr` or `?lang=en` (session).

Documentation:

- English: this file (`README.md`)
- Turkish: [`README.tr.md`](README.tr.md)

---

## Project structure

```
betech/
├── app/
│   ├── Commands/         # CLI commands
│   ├── Controllers/      # HTTP / API endpoints
│   ├── Middleware/       # Auth, roles, CSRF, rate limit, security headers
│   ├── Models/           # Medoo data access
│   └── Services/         # Domain services, IPAM, mail, backups, QR, …
├── config/               # app, database, bootstrap, r2
├── database/
│   ├── schema.sql
│   ├── seeds.sql
│   └── migrations/       # Incremental upgrades (001–033+)
├── lang/                 # tr.php, en.php
├── public/               # Web root (index.php, .htaccess, assets)
├── resources/css/        # Tailwind source
├── views/                # PHP templates + Alpine.js UI
├── cli.php               # CLI entrypoint
├── deploy.sh             # Production pull + composer
├── README.md             # English documentation
└── README.tr.md          # Turkish documentation
```

---

## Security notes

- Prefer HTTPS in production; set `APP_URL` to the canonical HTTPS origin.
- Session cookies: `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS.
- Login rate limiting (`login_attempts`), CSRF on state-changing requests, security headers (HSTS on HTTPS, CSP, frame deny).
- Set `APP_ENV=production` and `DISPLAY_ERROR_DETAILS=false`.
- Configure `TRUSTED_PROXIES` when behind Cloudflare/WAF.
- Never commit `.env` or secrets; keep MySQL/LDAP reachable only from app servers.

---

## License

Released under **GNU GPL v3.0 or later** (`GPL-3.0-or-later`). See `composer.json` and `LICENSE`.

---

## Repository

https://github.com/ardacetin/betech
