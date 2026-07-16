# BT Yönetim Sistemi (ITMS) / Betech

[English README](README.md)

**BT Yönetim Sistemi (ITMS)**, kuruluşunuzun kendi altyapısında çalışan, açık kaynaklı ve kendi kendine barındırılan (self-hosted) bir BT envanter, yardım masası, ağ/IP yönetimi ve operasyon platformudur. Çok kiracılı SaaS ürünlerinin aksine ITMS **tek kuruluş / tek veritabanı** olarak sizin sunucularınızda çalışır; veri, kimlik doğrulama ve ağ sınırları sizin kontrolünüzdedir.

**PHP 8.1+**, **Slim 4**, **Medoo**, **MySQL**, **Alpine.js** ve **Tailwind CSS** ile geliştirilmiştir. Excel/CSV aktarımları için **PhpSpreadsheet** kullanılır. İsteğe bağlı entegrasyonlar: LDAP, Google/Microsoft SSO, SMTP, IMAP gelen kutusu, Cloudflare Turnstile, Telegram uyarıları ve Cloudflare R2 yedekleme.

---

## İçindekiler

1. [Öne çıkan özellikler](#öne-çıkan-özellikler)
2. [Roller ve menü](#roller-ve-menü)
3. [Mimari](#mimari)
4. [Sistem gereksinimleri](#sistem-gereksinimleri)
5. [Kurulum](#kurulum)
6. [Güncelleme](#güncelleme)
7. [CLI araçları](#cli-araçları)
8. [Çoklu dil](#çoklu-dil)
9. [Proje yapısı](#proje-yapısı)
10. [Güvenlik notları](#güvenlik-notları)
11. [Lisans](#lisans)

---

## Öne çıkan özellikler

### Çok biçimli envanter (Varlık Yönetimi)

- Varlıklar, yapılandırılabilir **varlık tiplerine** göre ayrı tablolarda tutulur (`assets_{slug}`; örn. Switchler, dizüstü, yazıcı).
- Temel alanlar: demirbaş no, ad, seri no, durum, konum, zimmet ve tipe özel sütunlar.
- Oluşturmada otomatik **ENV-####** demirbaş numarası (örn. `ENV-0001`).
- Tip bazında özel alanlar ve donanım bileşenleri.
- Tip farkındalığı olan bağımsız **Ekle / Düzenle** sayfaları (`/inventory/add`, `/inventory/edit`).
- Envanter için CSV/Excel **içe aktarma** ve **dışa aktarma**.
- Opak ve iptal edilebilir token’lı QR etiketleri: `/assets/view/{token}` (yönetici alan görünürlüğünü kontrol eder; seri/MAC varsayılan gizli).
- Zimmet akışları: atama, depoya iade, personele devir, işten çıkış geri toplama, zimmet tutanağı (Quill HTML şablonları).

### Yardım masası

- Destek talepleri: oluşturma, durum, yorumlar, talep kategorileri.
- Son kullanıcı **portalı**: taleplerim, yayınlanmış bilgi bankası, zimmetli varlıklarım.
- İsteğe bağlı **IMAP gelen kutusu** (`php cli.php mail:fetch_inbox`) ile e-postadan talep açma.
- Talep olayları için SMTP bildirimleri (Ayarlar üzerinden).

### Bilgi bankası ve kalite dokümanları

- Operatör ve son kullanıcı için bilgi makaleleri (taslak/yayında).
- Kalite / BT politika doküman kütüphanesi (yükleme ve indirme).

### Sarf malzemeler ve yazılım lisansları (SAM)

- Sarf stok yönetimi (çıkış / stok ekleme).
- Koltuk (seat) kapasiteli lisanslar; koltuk bir cihaza **veya** bir kişiye bağlanır.
- Varlık detayında o donanıma atanmış lisanslar listelenebilir.

### Ağ & IP Yönetimi

- IP ağları (alt ağ / VLAN) ve doluluk göstergeleri.
- Ağ bazında IP adresi ızgarası: durum filtreleri, toplu düzenleme, not, hostname, MAC, demirbaş.
- ICMP **ping** durumu ve **rogue** (izinsiz) cihaz işaretleri.
- **Excel/CSV’den içe aktarma** (ağ veya adres) ve **Excel/CSV’ye dışa aktarma**:
  - Ağ listesi: `GET /api/ip-networks/export`
  - Tek ağın adresleri: `GET /api/ip-networks/{id}/export`

### Switch Port Yönetimi

- Envanterdeki switch varlık tiplerinden (eski tablolar dahil) switch dizini.
- Switch başına görsel **port matrisi**.
- Port yapılandırmasında bağlı olanı anlatan **serbest metin açıklama** (envanterden cihaz bağlama zorunlu değildir).
- Rotalar: `/network/switch-ports`, `/network/port-config`, `/switch-ports.php`.

### Bakım / onarım takibi

- Onarımda varlıklar için bakım kayıtları şeması ve API (servis, maliyet, tarihler, durum).

### Personel ve sistem kullanıcıları

- **Sistem kullanıcıları** (`admin`): ITMS operasyon paneline giren hesaplar.
- **Personel** (`user`): zimmet için rehber kişileri; yalnızca self-servis portal.
- LDAP / Google rehber **senkronizasyonu** (sayfalı) yerel personel tablosuna.
- Rehberde bulunamayanlar için manuel personel ekleme.

### Kimlik doğrulama

| Sağlayıcı | Yöntem |
|-----------|--------|
| Yerel veritabanı | E-posta ve parola |
| LDAP / Active Directory | Doğrudan kullanıcı bağlama |
| Google Workspace | OAuth 2.0 |
| Microsoft 365 | Azure AD OAuth 2.0 + Graph |

İsteğe bağlı giriş CAPTCHA: **Cloudflare Turnstile**.

### Analitik, raporlar, denetim ve yedekleme

- Genel bakış kartları ve aktivite.
- Yardım masası / operasyon raporları.
- Sistem denetim logları.
- Veritabanı yedekleri (yerel + isteğe bağlı **Cloudflare R2**).
- CLI: günlük özet e-posta, sağlık taraması (Telegram), anlık yedek.

### Otomatik veritabanı kurulumu

İlk istekte `public/index.php`, `DatabaseInitializer` ile `database/schema.sql`, `database/migrations/` altındaki artımlı migrasyonları ve `database/seeds.sql` dosyasını uygular. Ayrı bir kurulum sihirbazı yoktur.

---

## Roller ve menü

ITMS iki oturum rolü kullanır (`super_admin` / `technician` / `end_user` gibi eski adlar bunlara normalize edilir):

| Rol | Arayüz | Erişim |
|-----|--------|--------|
| **Admin** (`admin`) | Operasyon paneli | Tam operasyonel UI: envanter, yardım masası, IPAM, switch portları, lisanslar, ayarlar, raporlar, yedekler |
| **Kullanıcı** (`user`) | Son kullanıcı portalı | Kendi varlıkları, kendi talepleri, yayınlanmış bilgi bankası |

Tipik admin kenar çubuğu bölümleri:

- **Operasyon** — Yardım masası, bilgi bankası, raporlar, kalite dokümanları  
- **Varlık yönetimi** — Tip bazlı envanter listeleri, sarf, lisanslar, personel  
- **Altyapı** — Ağ & IP Yönetimi, Switch Port Yönetimi  
- **Sistem** — Ayarlar / yedekleme, sistem kullanıcıları, denetim logları, varlık tipi yapılandırması  

Varsayılan seed admin (üretimde hemen değiştirin):

| Alan | Değer |
|------|-------|
| E-posta | `admin@betech.local` |
| Parola | `admin123` |

---

## Mimari

```
┌─────────────────────────────────────────────────────────────┐
│  Sizin ağınız (yerinde veya özel bulut)                     │
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐  │
│  │ Nginx/Apache │───▶│  PHP-FPM     │───▶│  MySQL       │  │
│  │  → public/   │    │  Slim 4 app  │    │              │  │
│  └──────────────┘    └──────────────┘    └──────────────┘  │
│                              │                              │
│                              ├── LDAP (isteğe bağlı)        │
│                              ├── Google / Microsoft OAuth   │
│                              ├── SMTP / IMAP (isteğe bağlı) │
│                              └── R2 / Telegram (isteğe bağlı)│
└─────────────────────────────────────────────────────────────┘
```

Tüm uygulama durumu MySQL örneğinizdedir. ITMS, satıcı barındırmalı bir arka uç gerektirmez.

---

## Sistem gereksinimleri

| Bileşen | Gereksinim |
|---------|------------|
| **PHP** | 8.1+ (`ext-json`, `ext-pdo_mysql`, `ext-curl`; isteğe bağlı: `ext-ldap`, `ext-imap`, spreadsheet için `ext-gd`/`ext-zip`) |
| **Veritabanı** | MySQL 5.7.8+ veya MariaDB 10.2+ |
| **Composer** | 2.x |
| **Web sunucusu** | Apache 2.4+ (`mod_rewrite`) veya Nginx 1.18+ |
| **Node (geliştirme)** | İsteğe bağlı Tailwind derlemesi (`npm run build`) |
| **İşletim sistemi** | Linux önerilir (geliştirme için macOS uygun) |

---

## Kurulum

### 1. Depoyu klonlayın

```bash
git clone https://github.com/ardacetin/betech.git
cd betech
```

### 2. PHP bağımlılıkları

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Ortam yapılandırması

```bash
cp .env.example .env
```

Minimum `.env` değerleri:

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

Veritabanı oluşturma:

```sql
CREATE DATABASE betech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'betech'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON betech.* TO 'betech'@'localhost';
FLUSH PRIVILEGES;
```

İsteğe bağlı `.env` alanları (bir kısmı Yönetici arayüzünden de ayarlanır): SMTP, IMAP, Telegram, Turnstile, Cloudflare R2. Ayrıntılar için `.env.example`.

### 4. Web sunucusu document root

Sanal host **document root** değeri mutlaka `public/` olmalıdır (proje kökü değil).

#### Nginx (özet)

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

`mod_rewrite` etkin olsun. `public/.htaccess` istekleri `index.php`’ye yönlendirir.

### 5. İlk açılış

Uygulama URL’sini açın. `DatabaseInitializer` şemayı oluşturur/günceller ve varsayılan verileri yükler. Hata olursa web loglarında `[Betech]` kayıtlarına bakın.

### 6. Giriş ve sıkılaştırma

1. Seed admin ile giriş yapıp parolayı değiştirin.
2. **Ayarlar** üzerinden kimlik doğrulama, SMTP ve rehber senkronunu yapılandırın.
3. SSO için OAuth yönlendirme URI’leri (`APP_URL` ile eşleşmeli):
   - `{APP_URL}/auth/callback/google`
   - `{APP_URL}/auth/callback/microsoft`

---

## Güncelleme

Üretim sunucusunda:

```bash
./deploy.sh
```

Bu komut `origin/main` çeker, `composer install --no-dev --optimize-autoloader` çalıştırır ve varsa `var/cache/` temizler. Ardından bekleyen migrasyonların uygulanması için siteyi bir kez açın.

---

## CLI araçları

Proje kökünden:

```bash
php cli.php make:admin <username>     # LDAP personelini admin yap (önce bir kez giriş)
php cli.php mail:fetch_inbox          # Destek gelen kutusu → talepler (ext-imap + config)
php cli.php notify:daily_summary      # Günlük operasyon özeti e-postası
php cli.php notify:health_scan        # Sağlık tarama uyarıları (isteğe bağlı Telegram)
php cli.php backup:database           # Veritabanı yedeği (isteğe bağlı R2)
```

IMAP çekimini ve sağlık taramasını cron ile zamanlayın (gelen kutu için 5–15 dakika).

---

## Çoklu dil

| Dil | Kod | Not |
|-----|-----|-----|
| Türkçe | `tr` | Varsayılan arayüz dili |
| İngilizce | `en` | Alternatif arayüz dili |

Dosyalar: `lang/tr.php`, `lang/en.php`. `?lang=tr` veya `?lang=en` (oturumda saklanır).

Dokümantasyon:

- Türkçe: bu dosya (`README.tr.md`)
- İngilizce: [`README.md`](README.md)

---

## Proje yapısı

```
betech/
├── app/
│   ├── Commands/         # CLI komutları
│   ├── Controllers/      # HTTP / API uç noktaları
│   ├── Middleware/       # Auth, roller, CSRF, rate limit, güvenlik başlıkları
│   ├── Models/           # Medoo veri erişimi
│   └── Services/         # Alan servisleri, IPAM, mail, yedek, QR, …
├── config/               # app, database, bootstrap, r2
├── database/
│   ├── schema.sql
│   ├── seeds.sql
│   └── migrations/       # Artımlı yükseltmeler (001–033+)
├── lang/                 # tr.php, en.php
├── public/               # Web kökü (index.php, .htaccess, statik dosyalar)
├── resources/css/        # Tailwind kaynağı
├── views/                # PHP şablonları + Alpine.js arayüzü
├── cli.php               # CLI giriş noktası
├── deploy.sh             # Üretim pull + composer
├── README.md             # İngilizce dokümantasyon
└── README.tr.md          # Türkçe dokümantasyon
```

---

## Güvenlik notları

- Üretimde HTTPS kullanın; `APP_URL` kanonik HTTPS adresi olsun.
- Oturum çerezleri: `HttpOnly`, `SameSite=Lax`, HTTPS’te `Secure`.
- Giriş hız sınırlama (`login_attempts`), durum değiştiren isteklerde CSRF, güvenlik başlıkları (HTTPS’te HSTS, CSP, frame deny).
- `APP_ENV=production` ve `DISPLAY_ERROR_DETAILS=false` ayarlayın; fatal/çalışma zamanı ayrıntıları kullanıcıya değil log’a yazılsın.
- QR envanter sayfaları ardışık ID yerine tahmin edilemeyen token kullanır; bağlantıyı envanter detayından iptal/yenileyin, erişim ve alanları Ayarlar’dan yönetin.
- Cloudflare/WAF arkasındaysanız `TRUSTED_PROXIES` tanımlayın.
- `.env` ve sırları asla commit etmeyin; MySQL/LDAP erişimini yalnızca uygulama sunucularına açın.

---

## Lisans

**GNU GPL v3.0 veya sonrası** (`GPL-3.0-or-later`) ile yayınlanır. `composer.json` ve `LICENSE` dosyalarına bakın.

---

## Depo

https://github.com/ardacetin/betech
