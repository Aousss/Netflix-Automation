# 2. Technical Requirement Documentation (TRD)
**Project Name:** Netflix Household & Split-Billing Portal ("Netflix Household Hub")  
**Document Version:** 1.0  
**Framework:** Laravel 11.x (PHP 8.2+)

---

## 1. System Architecture Overview

The application follows a cloud-native **Laravel 11 MVC + Service Layer** architecture designed for **Laravel Cloud** with **TiDB Cloud Serverless** (`mysql` over TLS port `4000`) and **Firebase Authentication** (`arise-26`).

```mermaid
flowchart LR
    subgraph External["External Services"]
        NF["Netflix Account Mailer<br/>(info@account.netflix.com)"]
        GM["Admin Primary Gmail<br/>(Auto-Forward Filter)"]
        FB["Firebase Auth (arise-26)<br/>Google Sign-In Provider"]
        IMAP["Dedicated IMAP Mailbox<br/>(SSL Port 993)"]
    end

    subgraph LaravelCloud["Laravel Cloud (Compute & Scheduler)"]
        AUTH["GoogleAuthController<br/>(Firebase ID Token + Magic Link)"]
        DASH["DashboardController<br/>(Bento Grid + IMAP JSON)"]
        IMAPS["NetflixImapService<br/>(ext-imap + Pure-PHP SSL Socket)"]
        VAULT["VaultController<br/>(Step-Up OTP + AES-256)"]
        PAY["PaymentController<br/>(DuitNow Receipt Storage)"]
        ADMIN["AdminController<br/>(Verify + Settings + Cron)"]
    end

    subgraph DataLayer["Managed Cloud Persistence"]
        TIDB[("TiDB Cloud Serverless<br/>MySQL TLS :4000<br/>Users, Vault, Ledger, Sessions")]
        OBJ[("Laravel Cloud Object Storage<br/>(or Local Disk)<br/>Receipts & DuitNow QR")]
    end

    NF -->|"Verification Email"| GM
    GM -->|"Filter: temporary access code<br/>OR Netflix Household"| IMAP
    IMAP -->|"SSL Port 993 (Read-Only)"| IMAPS
    FB <-->|"ID Token Verification<br/>(Identity Toolkit API)"| AUTH
    DASH --> IMAPS
    AUTH & DASH & VAULT & PAY & ADMIN --> TIDB
    PAY & ADMIN --> OBJ
```

---

## 2. Technology Stack & Dependencies

### 2.1 Backend Stack
| Component | Requirement / Package | Purpose |
| :--- | :--- | :--- |
| **Language** | PHP `^8.2` | Core runtime with strict typing, enums, and modern cryptography (`openssl`). |
| **Framework** | `laravel/framework: ^11.0` | Routing, Eloquent ORM, AES-256 encryption casts, caching, mail, and scheduling. |
| **OAuth / Identity** | Firebase Auth (`arise-26`) + `laravel/socialite: ^5.16` | Client-side Google Sign-In popup + backend Firebase ID Token verification via Google Identity Toolkit REST API. |
| **IMAP Client** | PHP `ext-imap` + Pure-PHP SSL Socket fallback (`stream_socket_client`) | Read-only SSL connection (`port 993`) that works on Laravel Cloud containers even without `ext-imap` installed. |
| **HTML Parser** | PHP `ext-dom` (`DOMDocument`) + PCRE | Extracts Netflix `nftoken` URLs, 4-digit codes, requesting profile names, and device names. |
| **Database** | **TiDB Cloud Serverless** (MySQL 8.0 compatible over TLS `:4000`) / SQLite 3 | Distributed relational persistence for users, vault settings, billing cycles, payments, sessions, cache, and audit logs. |

### 2.2 Frontend Stack
| Component | Technology | Purpose |
| :--- | :--- | :--- |
| **Templating** | Laravel Blade (`resources/views/`) | Server-rendered HTML with CSRF protection and role-aware conditional rendering. |
| **Styling** | Tailwind CSS | Utility-first dark-mode Bento Grid styling (`bg-zinc-950`, `bg-zinc-900/80`, `red-600` accents). |
| **Interactivity** | Alpine.js `3.x` | Reactive state management (`householdPortal` component) for AJAX code fetching, live countdown timers, OTP modals, clipboard actions, and 15-second password auto-remasking. |

---

## 3. Authentication & Security Architecture

### 3.1 Multi-Mode Whitelist Authentication ([`GoogleAuthController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/GoogleAuthController.php))
1. **Primary Mode — Google OAuth 2.0:**
   * `GET /auth/google` redirects to Google OAuth consent screen.
   * `GET /auth/google/callback` retrieves the Google profile email and queries `User::whereRaw('LOWER(email) = ?', [$email])->first()`.
   * If the user does not exist or `!$user->is_active`, the request is rejected with `HTTP 403 Forbidden` and logged in `audit_logs` (`auth.login_failed`).
2. **Fallback Mode — Passwordless Signed Magic Link:**
   * `POST /auth/magic-link` validates that the email belongs to an active whitelisted member and generates a 15-minute signed URL via `URL::temporarySignedRoute('auth.magic.verify', now()->addMinutes(15), ['user' => $user->id])`.
   * When `APP_DEBUG=true` or `MAIL_MAILER=log`, the signed URL is also flashed to `magic_link_preview` for instant local verification.
3. **Development Mode — Quick Household Switcher:**
   * `POST /auth/demo-switch/{user}` is strictly guarded by `if (!app()->environment('local') && !config('app.debug')) abort(403);`.
   * Clears `vault_verified_until` session state on switch to ensure accurate testing of step-up OTP flows per member.

### 3.2 Encrypted Vault & Step-Up OTP Verification ([`VaultController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/VaultController.php))
* **At-Rest Encryption:**
  * [`AccountSetting::$casts`](file:///c:/laragon/www/netflix-automation/app/Models/AccountSetting.php#L15-L23) casts `netflix_password` as `'encrypted'`.
  * [`User::$casts`](file:///c:/laragon/www/netflix-automation/app/Models/User.php#L21-L31) casts `netflix_profile_pin` as `'encrypted'`.
  * Both fields are listed in `$hidden` on their respective Eloquent models to prevent accidental serialization.
* **Payment Gating (`HTTP 402`):**
  * [`User::canAccessVault(?Payment $currentPayment)`](file:///c:/laragon/www/netflix-automation/app/Models/User.php#L54-L69) returns `false` when `$currentPayment->status === 'overdue'` unless the user is an Admin or has an active 24-hour emergency grace bypass (`grace_bypass_used_at > now()->subHours(24)`).
* **Step-Up OTP Lifecycle:**
  * `POST /vault/request-otp` generates a CSPRNG 6-digit integer (`random_int(100000, 999999)`), stores it in `Cache::put("vault_otp_{$user->id}", $code, now()->addMinutes(5))`, and emails it to the user.
  * `POST /vault/reveal` compares the submitted OTP using timing-attack-safe `hash_equals()`, consumes the cache key, sets `session('vault_verified_until', now()->addMinutes(15))`, records `vault.password_revealed` in `audit_logs`, and returns the decrypted credentials with `remask_after_seconds: 15`.

---

## 4. IMAP Verification Engine Specification ([`NetflixImapService.php`](file:///c:/laragon/www/netflix-automation/app/Services/NetflixImapService.php))

### 4.1 Gmail Auto-Forwarding Isolation Rule
To guarantee the Admin's personal Gmail inbox is never exposed to the web server, the Admin configures a Gmail filter forwarding **only** matching messages to `netflix@yourdomain.com`:
```text
from:(info@account.netflix.com) ("temporary access code" OR "Netflix Household" OR "update-primary-location")
```

### 4.2 IMAP Connection & Message Filtering
* **Mailbox String:** `{mail.yourdomain.com:993/imap/ssl/novalidate-cert}INBOX`
* **Access Mode:** `OP_READONLY` (never mutates or deletes emails on the mail server).
* **Search Criteria:** `SINCE "<yesterday>" FROM "info@account.netflix.com"` (using yesterday's date to avoid timezone boundary misses around midnight).
* **Recency Filter:** Iterates over the 10 newest messages in reverse chronological order (`rsort`) and keeps only messages where `$timestamp >= now()->subMinutes(15)->timestamp`.

### 4.3 HTML Decoding & Extraction Rules
1. **Decoding:** Detects Quoted-Printable encoding (`=3D` or soft line breaks `=\r\n`) and applies `quoted_printable_decode()`, followed by `html_entity_decode(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')`.
2. **Approval Link Regex:**
   ```regex
   /https:\/\/www\.netflix\.com\/account\/(travel\/verify|update-primary-location)[^\s"'<>]+/i
   ```
   Unescapes `&amp;` to `&`.
3. **4-Digit Code Regex:**
   * Primary: `/(?:code|pin|enter)\s*(?:is|:)?\s*<[^>]+>\s*(\d{4})\s*<\//i`
   * Fallback: `/\b(\d{4})\b/` on stripped text (excluding calendar years `2020–2035`).
4. **Metadata Extraction:** Extracts `profile_name` (`Requested by <Name>`) and `device_type` (`Device: <Device>`) via `DOMDocument` text normalization.

---

## 5. HTTP Route & API Contract Specification ([`routes/web.php`](file:///c:/laragon/www/netflix-automation/routes/web.php))

| Method | URI | Route Name | Middleware / Rate Limit | Controller Action | Response Type |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `GET` | `/` | — | `web` | Closure redirect | `302` to `/dashboard` or `/login` |
| `GET` | `/login` | `login` | `guest` | `GoogleAuthController@showLogin` | Blade View (`auth.login`) |
| `GET` | `/auth/google` | `auth.google` | `guest` | `GoogleAuthController@redirectToGoogle` | `302` to Google OAuth |
| `GET` | `/auth/google/callback` | `auth.google.callback` | `guest` | `GoogleAuthController@handleGoogleCallback` | `302` to `/dashboard` or `403` |
| `POST` | `/auth/magic-link` | `auth.magic.send` | `guest`, `throttle:5,1` | `GoogleAuthController@sendMagicLink` | `302` back with status flash |
| `GET` | `/auth/magic-link/verify/{user}` | `auth.magic.verify` | `web` (signed check inside) | `GoogleAuthController@verifyMagicLink` | `302` to `/dashboard` or `403` |
| `POST` | `/auth/demo-switch/{user}` | `auth.demo.switch` | `web` (`local`/`debug` only) | `GoogleAuthController@demoSwitch` | `302` to `/dashboard` |
| `POST` | `/logout` | `logout` | `auth` | `GoogleAuthController@logout` | `302` to `/login` |
| `GET` | `/dashboard` | `dashboard` | `auth` | `DashboardController@index` | Blade View (`dashboard`) |
| `POST` | `/imap/fetch` | `imap.fetch` | `auth`, `throttle:10,1` | `DashboardController@fetchImapCodes` | `application/json` |
| `POST` | `/vault/request-otp` | `vault.request-otp` | `auth`, `throttle:3,1` | `VaultController@requestOtp` | `application/json` (`200` or `402`) |
| `POST` | `/vault/reveal` | `vault.reveal` | `auth`, `throttle:6,1` | `VaultController@reveal` | `application/json` (`200`, `402`, `422`) |
| `POST` | `/vault/grace-bypass` | `vault.grace-bypass` | `auth` | `VaultController@activateGraceBypass` | `application/json` (`200` or `422`) |
| `POST` | `/payments/{payment}/upload-receipt` | `payments.upload-receipt` | `auth` | `PaymentController@uploadReceipt` | `302` back with status flash |
| `GET` | `/payments/{payment}/receipt` | `payments.receipt` | `auth` (Owner or Admin) | `PaymentController@showReceipt` | File stream (`image/*`, `application/pdf`, `image/svg+xml`) |
| `POST` | `/admin/payments/{payment}/verify` | `admin.payments.verify` | `auth` (Admin check) | `AdminController@verifyPayment` | `302` back with status flash |
| `POST` | `/admin/payments/{payment}/status` | `admin.payments.status` | `auth` (Admin check) | `AdminController@updatePaymentStatus` | `302` back with status flash |
| `POST` | `/admin/settings` | `admin.settings.update` | `auth` (Admin check) | `AdminController@updateSettings` | `302` back with status flash |
| `POST` | `/admin/members/{member}` | `admin.members.update` | `auth` (Admin check) | `AdminController@updateMember` | `302` back with status flash |
| `POST` | `/admin/billing/generate-cycle` | `admin.billing.generate` | `auth` (Admin check) | `AdminController@generateCycle` | `302` back with status flash |

---

## 6. CLI Commands & Scheduled Tasks ([`routes/console.php`](file:///c:/laragon/www/netflix-automation/routes/console.php))

### 6.1 `php artisan billing:generate-cycle {month?}`
* **Purpose:** Idempotently creates a `BillingCycle` record for `YYYY-MM` (defaults to current month) and initializes `Payment` rows for all active members.
* **Business Rules:**
  * Due date is calculated as `billing_day_of_month + 5 days`.
  * Admin's own share is automatically marked `paid` (`Subscription Leader Share`).
  * Members with `credit_balance >= monthly_share_amount` have their credit balance decremented automatically and their payment marked `paid` (`Auto-deducted from Advance Credit Balance`).
  * Remaining active members receive a `pending` payment row.
* **Schedule:** Runs automatically on the 1st of every month at `00:05` via `Schedule::command('billing:generate-cycle')->monthlyOn(1, '00:05')`.

---

## 7. Environment Configuration (`.env`) for Laravel Cloud + TiDB Cloud

```ini
APP_NAME="Netflix Household Hub"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_TIMEZONE=Asia/Kuala_Lumpur
APP_URL=https://netflix-hub.laravel.cloud

# TiDB Cloud Serverless (MySQL-Compatible over TLS Port 4000)
DB_CONNECTION=mysql
DB_HOST=gateway01.ap-southeast-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_DATABASE=netflix_portal
DB_USERNAME=xxxx.root
DB_PASSWORD=your-tidb-cloud-password
DB_COLLATION=utf8mb4_unicode_ci
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

# Database-backed Sessions & Cache (Stateless Laravel Cloud Compute)
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Firebase Web Auth Configuration (Google Sign-In — Project: arise-26)
FIREBASE_API_KEY="AIzaSyA_BwqDRzrU37fZXzVbQ02wHK8ugrD1Eq4"
FIREBASE_AUTH_DOMAIN="arise-26.firebaseapp.com"
FIREBASE_PROJECT_ID="arise-26"
FIREBASE_STORAGE_BUCKET="arise-26.firebasestorage.app"
FIREBASE_MESSAGING_SENDER_ID="166673631179"
FIREBASE_APP_ID="1:166673631179:web:ea61f8df46954dd388d84e"
FIREBASE_MEASUREMENT_ID="G-TEMJT4GVCP"

# Dedicated IMAP Mailbox for Auto-Forwarded Netflix Verification Emails
NETFLIX_IMAP_HOST=imap.gmail.com
NETFLIX_IMAP_PORT=993
NETFLIX_IMAP_ENCRYPTION=ssl
NETFLIX_IMAP_USER=your-dedicated-netflix-inbox@gmail.com
NETFLIX_IMAP_PASS=your-16-char-app-password
```

