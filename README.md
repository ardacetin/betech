# BT Yönetim Sistemi (ITMS)

**BT Yönetim Sistemi (ITMS)** is a lightweight, open-source, self-hosted platform for tracking IT inventory, personnel assignments, and operational workflows inside your own infrastructure. Unlike multi-tenant SaaS products, ITMS runs as a **standalone application** on your servers: one organization, one database, full control over data, authentication, and network boundaries.

Built with **PHP 8.1+**, **Slim 4**, **Medoo**, **MySQL** (with native JSON columns), **Alpine.js**, and **Tailwind CSS**, the system combines relational integrity for core records with flexible JSON properties for category-specific technical fields—without external cloud dependencies.

---

## Key Features

### Envanter Yönetimi (Inventory Management)

- Fixed relational columns for operational data (`asset_tag`, `serial_number`, `name`, `status`, `user_id`, `location_id`, `category_id`).
- **Auto-generated inventory tags** in sequential `ENV-####` format (e.g. `ENV-0001`, `ENV-0002`) assigned on create—no manual tag entry required. Tags drive QR code generation on the dashboard.
- Dynamic **JSON `properties`** column for per-category technical attributes (default MAC address fields, RAM, CPU, ports, IP address, and custom fields).
- Global optional custom fields configurable from **Sistem Ayarları**.

### Category-driven dynamic forms

- Category definitions include a JSON `fields` schema (text, number, textarea).
- The dashboard loads field definitions at runtime and renders inputs with **Alpine.js**—no code changes required when categories evolve.
- **Super Admins** manage categories from **Sistem Ayarları → Kategoriler**: create, edit, and delete categories with a built-in **dynamic field builder** (label, internal name, and type per field).
- Category CRUD is exposed via `GET/POST/PUT/DELETE /api/categories`; the `fields` payload is persisted to the `categories.fields` JSON column and immediately drives asset create/edit forms.
- Deletion is blocked when assets still reference the category, preventing orphaned records.

### Physical location and room-based asset tracking

- **Locations** table stores campus/building context (`name`, `building`, `description`) for classrooms, server rooms, and other physical areas.
- Assets may have a `user_id`, a `location_id`, or both—personnel assignment and room placement are independent.
- **Super Admins** manage locations from **Sistem Ayarları → Lokasyonlar** with full CRUD via `GET/POST/PUT/DELETE /api/locations`.
- Location changes are written to `asset_histories` (e.g. “Varlık lokasyona taşındı: Sunser Odası”).
- Legacy databases self-heal on boot: `DatabaseInitializer` creates the `locations` table and adds `assets.location_id` when missing.

### Software Asset Management (SAM) with seat capacity tracking and hardware/user mapping

- **Licenses** table tracks corporate software (`name`, `vendor`, optional `license_key`, `seats`, `expiration_date`, `notes`).
- **License assignments** map each seat to either an `asset_id` (device) or a `user_id` (person)—never both on the same row.
- `GET /api/licenses` returns `assigned_seats` and `remaining_seats` for each license; assignment is blocked when no seats remain.
- **Super Admins and Technicians** manage licenses from the **Yazılım & Lisanslar** dashboard tab with add/assign/unassign workflows.
- Asset detail view lists software licenses currently assigned to that hardware via `GET /api/assets/{id}/licenses`.
- Legacy databases self-heal on boot: `DatabaseInitializer` creates `licenses` and `license_assignments` when missing.

### Automated database setup

- On first request, `public/index.php` runs `DatabaseInitializer`, which applies `database/schema.sql`, incremental migrations, and `database/seeds.sql`.
- No separate installer wizard: configure `.env`, point your web server at `public/`, and visit the application URL.

### Role-based access control (RBAC)

BT Yönetim Sistemi (ITMS) enforces three session-scoped roles stored on the `users.role` column. `RoleMiddleware` guards API routes; the dashboard hides navigation and actions based on the active role.

| Role | Scope |
|------|--------|
| **Super Admin** (`super_admin`) | Full access: Sistem Ayarları, auth driver configuration, permanent asset deletion, and all technician capabilities |
| **Technician** (`technician`) | Operational access: create/edit/assign assets, print zimmet tutanak forms, search personnel, offboarding workflow, executive analytics |
| **End User** (`end_user`) | Self-service only: simplified dashboard listing assets where `user_id` matches the signed-in user; may view history and print tutanak for own assignments |

The seeded local administrator (`admin@betech.local`) receives the `super_admin` role. SSO/LDAP auto-provisioned accounts default to `end_user` (Personnel) until promoted under **Sistem Kullanıcıları**.

### System Users vs Personnel (Operators vs Asset Holders)

ITMS separates **who operates the platform** from **who receives assets**:

| Concept | Turkish UI | Role | Purpose |
|---------|------------|------|---------|
| **System Users** | Sistem Kullanıcıları | `super_admin`, `technician` | Accounts that sign in to the BTMS operations dashboard. Super Admins manage these accounts and assign roles. |
| **Personnel** | Personel / Personel Rehberi | `end_user` | Employees synced from LDAP/Google (or added manually for zimmet). Used for asset assignment, transfer, and offboarding—not for granting dashboard operator access. |

API endpoints:

- `GET /api/system-users`, `POST /api/system-users`, `PUT /api/system-users/{id}` — Super Admin only; operator account lifecycle.
- `GET /api/personnel` (alias: `GET /api/users`) — Technicians and Super Admins; personnel directory for zimmet workflows.
- `POST /api/users` — Manual personnel record for zimmet when directory search has no match (`end_user`).

The main sidebar stays operational (**Envanterler**, **Yazılım & Lisanslar**, **Personel**, **Sistem Kullanıcıları**). Configuration (**Kategoriler**, **Lokasyonlar**, auth, zimmet template) lives under **Sistem Ayarları** tabs.

### Enterprise multi-provider authentication

Self-hosted sign-in with admin-configurable providers (no shared identity pool):

| Provider | Method |
|----------|--------|
| **Local database** | Email and password (`users.password_hash`) |
| **LDAP / Active Directory** | Direct user bind against your directory |
| **Google Workspace** | OAuth 2.0 authorization code flow |
| **Microsoft 365** | Azure AD OAuth 2.0 + Microsoft Graph |

First-time SSO/LDAP users are **auto-provisioned** into the local `users` table with provider metadata. Directory integration for personnel search (LDAP, Google Admin SDK) is configured separately under **Kimlik Doğrulama Sürücüsü**.

### Lifecycle audit logging

- Every create, update, assignment, status change, and offboarding event is recorded in `asset_histories`.
- Full timeline available via `GET /api/assets/{id}/history`.

### Asset reclamation & offboarding

- **İşten Çıkış Sürecini Başlat** reclaims all assigned assets, sets asset status to `storage`, marks the user as `offboarded`, and writes audit entries automatically.

### One-click asset return and direct personnel transfer

Operational staff (**Super Admin** and **Technician**) can manage active assignments without editing the full asset form:

| Workflow | API | Behavior |
|----------|-----|----------|
| **Depoya İade Al** (Return to Storage) | `POST /api/assets/{id}/return` | Clears `user_id`, sets status to `ready`, and logs `Asset returned to IT storage.` in `asset_histories` |
| **Personele Devret** (Direct Transfer) | `POST /api/assets/{id}/transfer` | Accepts `{ "user_id": <id> }` and reassigns the asset directly to the new user; logs `Asset transferred from [Old User] to [New User].` |

Both actions are available from the asset list and the history/detail modal when an asset is currently assigned. The transfer dialog reuses the Alpine.js personnel search component for fast handoffs between employees.

### Manual local user creation (non-LDAP assignment)

When directory search (LDAP, Google Workspace, etc.) does not return a match, technicians can click **Manuel Kullanıcı Ekle** in the assignment picker to create a local user on the fly:

- Mini-form captures **Name** and **Email**.
- `POST /api/users` inserts a local `end_user` with `auth_provider = local`.
- The new user is selected immediately for zimmet assignment in the same modal flow.

### QR labels & mobile asset views

- **Print-ready SVG QR codes** for thermal labels (asset tag encoded in QR payload).
- Public, mobile-responsive asset detail page at `/assets/view/{id}` (ideal for field scans without logging into the dashboard).

### Executive analytics

- Dashboard summary cards, status distribution, category breakdown, and assignment metrics via `/api/analytics/summary`.

### Zimmet (assignment) tutanak

- **Rich text (WYSIWYG) corporate templates** via [Quill.js](https://quilljs.com/) (CDN)—bold text, ordered/unordered lists, alignment, and clean formatting without bloated dependencies.
- Templates are stored as HTML in the `settings` table and rendered on print with preserved structure.
- Placeholders: `{personnel_name}`, `{asset_name}`, `{serial_number}`, `{date}` (values are escaped for safe HTML output).
- Print-optimized output at `/api/assets/{id}/tutanak`.
- Legacy plain-text templates remain supported and are auto-formatted for print.

### System settings (Sistem Ayarları)

Administrators configure the self-hosted instance from the dashboard without code changes:

| Area | Capabilities |
|------|----------------|
| **Directory integration** | Active auth driver (local, LDAP, Google Workspace, Azure) for personnel search |
| **Login providers** | Enable local, LDAP, Google SSO, Microsoft 365 OAuth2 |
| **Zimmet template** | Quill.js rich text editor for corporate assignment forms |
| **Global custom fields** | Optional extra asset fields across all categories |
| **LDAP / Google directory** | Connection credentials for user search and zimmet assignment |

All settings persist in the `settings` table. Secrets are never returned by the API after save.

---

## Architecture (self-hosted)

```
┌─────────────────────────────────────────────────────────────┐
│  Your network (on-premise or private cloud)                 │
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐  │
│  │ Nginx/Apache │───▶│  PHP-FPM     │───▶│  MySQL       │  │
│  │  → public/   │    │  Slim 4 app  │    │  (JSON cols) │  │
│  └──────────────┘    └──────────────┘    └──────────────┘  │
│         │                    │                            │
│         │                    ├── LDAP (optional)           │
│         │                    ├── Google OAuth (optional)   │
│         │                    └── Microsoft OAuth (optional)│
└─────────────────────────────────────────────────────────────┘
```

All application state lives in your MySQL instance. ITMS does not require a vendor-hosted backend.

---

## System requirements

| Component | Requirement |
|-----------|-------------|
| **PHP** | 8.1 or newer (`ext-json`, `ext-pdo_mysql`, `ext-curl`; `ext-ldap` optional for LDAP login/directory) |
| **Database** | MySQL 5.7.8+ or MariaDB 10.2+ (JSON column support required) |
| **Composer** | 2.x |
| **Web server** | Apache 2.4+ with `mod_rewrite`, or Nginx 1.18+ |
| **OS** | Linux recommended (macOS suitable for development) |

---

## Installation (self-hosted)

### 1. Clone the repository

```bash
git clone https://github.com/ardacetin/betech.git
cd betech
```

### 2. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

For local development you may omit `--no-dev`.

### 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env` with your instance values:

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

Create the empty database before first boot:

```sql
CREATE DATABASE betech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'betech'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON betech.* TO 'betech'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Configure the web server

The **document root must be the `public/` directory**, not the project root.

#### Nginx

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

    location ~ /\. {
        deny all;
    }
}
```

#### Apache

Enable `mod_rewrite` and use a virtual host similar to:

```apache
<VirtualHost *:80>
    ServerName itms.yourcompany.local
    DocumentRoot /var/www/betech/public

    <Directory /var/www/betech/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

The included `public/.htaccess` forwards all requests to `index.php`.

### 5. First boot (automated database setup)

Open your application URL in a browser (e.g. `https://itms.yourcompany.local`).

On the first request, ITMS will:

1. Validate database credentials from `.env`.
2. Create tables from `database/schema.sql` if they do not exist.
3. Apply incremental migrations for existing installations.
4. Load default categories, sample users, and system settings from `database/seeds.sql`.

If initialization fails, the response is JSON with a descriptive error (check web server error logs for `[Betech]` or `[ITMS]` entries).

### 6. Sign in

Default local administrator (from seeds—**change immediately in production**):

| Field | Value |
|-------|-------|
| Email | `admin@betech.local` |
| Password | `admin123` |

Navigate to **Sistem Ayarları** to enable LDAP, Google, or Microsoft sign-in and configure integration credentials.

#### OAuth redirect URIs (when enabling SSO)

Register these in Google Cloud Console / Azure Portal, matching `APP_URL`:

- `{APP_URL}/auth/callback/google`
- `{APP_URL}/auth/callback/microsoft`

---

## Upgrading an existing installation

Use the deployment script on your server:

```bash
./deploy.sh
```

The script:

1. Pulls the latest `main` branch from GitHub.
2. Runs `composer install --no-dev --optimize-autoloader`.
3. Clears `var/cache/` if present.

After deployment, visit the application URL once so `DatabaseInitializer` can apply any pending migrations.

If you upgraded from a release **before** multi-provider authentication, manually apply the auth migration once:

```bash
mysql -u betech -p betech < database/migrations/005_add_user_auth_columns.sql
```

---

## Deployment script (`deploy.sh`)

`deploy.sh` is intended for **production servers** that already have the repository cloned and configured:

```bash
chmod +x deploy.sh
./deploy.sh
```

Prerequisites on the server:

- Git remote configured for `origin` (`main` branch).
- Composer available in `PATH`.
- Writable application directory for Composer vendor updates.

The script does not modify `.env` or web server configuration—those remain under your operational control.

---

## Localization (i18n)

ITMS ships with built-in internationalization:

| Locale | Code | Role |
|--------|------|------|
| Turkish | `tr` | **Default** UI language |
| English | `en` | Alternate UI language |

Translation files live in `lang/tr.php` and `lang/en.php`. Users can switch locale via `?lang=en` or `?lang=tr` (stored in session). The login page is presented in Turkish by design.

---

## Project structure

```
betech/
├── app/
│   ├── Controllers/      # HTTP endpoints (assets, auth, settings, users)
│   ├── Middleware/       # Language, authentication
│   ├── Models/           # Medoo data access
│   └── Services/         # Auth, QR, analytics, database bootstrap
├── config/               # app.php, database.php, bootstrap.php
├── database/
│   ├── schema.sql        # Full schema for fresh installs
│   ├── seeds.sql         # Default data
│   └── migrations/       # Incremental upgrades
├── lang/                 # tr.php, en.php
├── public/               # Web root (index.php, .htaccess)
├── views/                # PHP templates + Alpine.js dashboard
├── deploy.sh             # Production update helper
└── composer.json
```

---

## Security notes (self-hosted operators)

- Run ITMS behind HTTPS in production; set `APP_URL` to the canonical HTTPS origin.
- Change the default `admin@betech.local` password immediately after first login.
- Store LDAP bind passwords and OAuth client secrets only in the database settings table (never commit `.env` or secrets to Git).
- Restrict network access to MySQL and LDAP to application servers only.
- Keep PHP, MySQL, and ITMS updated via `deploy.sh` and your OS patch cycle.

---

## License

BT Yönetim Sistemi (ITMS) is released under the **GNU General Public License v3.0 or later** (GPL-3.0-or-later). See `composer.json` for the SPDX identifier.

---

## Repository

https://github.com/ardacetin/betech
