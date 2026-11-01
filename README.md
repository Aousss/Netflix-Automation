# Netflix Household Hub — Household Verification & Split-Billing Portal

A private, cinema-dark web portal built to manage a shared **5-member Netflix Premium 4K household subscription**. It eliminates manual WhatsApp code requests, protects the master account credentials behind payment gating and step-up Email OTP verification, and automates monthly DuitNow split-billing.

---

## Core Features

### 1. Live TV Household Verification Code Fetcher (15-Minute Window)
- Connects over read-only SSL IMAP (`ext-imap` with a built-in pure-PHP SSL socket fallback) to a dedicated mailbox receiving auto-forwarded Netflix verification emails (`info@account.netflix.com`).
- Parses Quoted-Printable HTML emails from the last **15 minutes** to extract:
  - **4-Digit Temporary Access Codes**
  - **`nftoken` Approval Links** (`travel/verify` and `update-primary-location`)
  - **Requesting Profile Name & Smart TV / Device Name**
  - **Live MM:SS Countdown Timer**

### 2. Encrypted Master Credentials Vault (Payment-Gated + Step-Up OTP)
- **AES-256-CBC Encryption at Rest:** Both the master Netflix password (`account_settings.netflix_password`) and each member's 4-digit Profile Lock PIN (`users.netflix_profile_pin`) are encrypted in the database.
- **Payment Gating (`HTTP 402`):** Members whose current monthly share is marked `overdue` are blocked from revealing the master password until they upload their transfer receipt (or activate their one-time **24-hour emergency grace bypass**).
- **Step-Up 6-Digit Email OTP:** Eligible members verify a 6-digit OTP (5-minute TTL) before decrypting the master password, which automatically re-masks on screen after **15 seconds**.

### 3. Shared Split-Billing & DuitNow Receipt Ledger
- **Automated Monthly Billing Cycles:** Scheduled command (`php artisan billing:generate-cycle`) runs on the 1st of every month to initialize member shares (`RM 11.00/member`) and automatically deducts from any member's prepaid **Advance Credit Balance**.
- **DuitNow QR & Receipt Uploads:** Members view the Admin's DuitNow QR code and bank details, upload transfer receipts (`JPG`, `PNG`, `PDF`), and track household settlement across a **6-month historical matrix**.
- **Admin Control Plane:** Slide-over drawer for one-click receipt approval/rejection, master password rotation, member whitelist management, and security audit logs (`audit_logs`).

### 4. Strict 5-Member Whitelist Authentication
- **Google Sign-In via Firebase Auth (`arise-26`):** Client-side Google popup verified server-side against Google's Identity Toolkit API and checked against the 5 whitelisted household emails.
- **Passwordless Signed Magic Links:** 15-minute cryptographically signed email login fallback (`URL::temporarySignedRoute`).
- **Local Demo Switcher:** Instant 1-click persona switching across all 5 seeded household roles when running in `local` development mode.

---

## Project Documentation (`docs/`)

Full engineering and product specifications are located in the [`docs/`](./docs) folder:

1. **[01 — Product Requirement Documentation (PRD)](./docs/01-PRD-Product-Requirements.md)**
2. **[02 — Technical Requirement Documentation (TRD)](./docs/02-TRD-Technical-Requirements.md)**
3. **[03 — App Flow Documentation (AFD)](./docs/03-AFD-App-Flow.md)**
4. **[04 — Design Brief Documentation (DBD)](./docs/04-DBD-Design-Brief.md)**
5. **[05 — Backend Schema Documentation (BSD)](./docs/05-BSD-Backend-Schema.md)**
6. **[06 — Implementation Plan Documentation (IPD)](./docs/06-IPD-Implementation-Plan.md)**
7. **[07 — Security Audit Report (SAR)](./docs/07-SAR-Security-Audit-Report.md)**

---

## Tech Stack

- **Backend:** Laravel 11.x (PHP 8.2+)
- **Database:** TiDB Cloud Serverless (MySQL-compatible over TLS port `4000`) / SQLite (local development)
- **Authentication:** Firebase Authentication (Google Sign-In) + Laravel Socialite + Signed Magic Links
- **Frontend:** Laravel Blade + Tailwind CSS (Cinema Dark Bento Grid) + Alpine.js 3.x
- **Hosting Target:** Laravel Cloud

---

## Quick Start (Local Development)

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy environment file and generate encryption key
cp .env.example .env
php artisan key:generate

# 3. Run migrations and seed the 5 demo household members + 6-month billing ledger
php artisan migrate --seed

# 4. Start the local server
php artisan serve
```

Visit **http://localhost:8000** and use the **Quick Access** panel on the login screen to test any of the 5 seeded household personas (`Firdaus` Admin, `Alex Tan` Paid + Advance Credit, `Sarah Lim` Verifying Receipt, `Amirul Hakim` Pending, `Chloe Wong` Overdue).

---

## Running Automated Tests

```bash
php artisan test
```

---

## Production Deployment (Laravel Cloud + TiDB Cloud)

1. **TiDB Cloud Serverless:** Create a cluster on [TiDB Cloud](https://tidbcloud.com/), create a `netflix_portal` database, and copy the host (`gateway01.*.prod.aws.tidbcloud.com`), port (`4000`), username, and password.
2. **Laravel Cloud Environment Variables:** Configure `DB_CONNECTION=mysql` with your TiDB credentials, `SESSION_DRIVER=database`, `CACHE_STORE=database`, and your `FIREBASE_*` & `NETFLIX_IMAP_*` variables (see [`.env.example`](./.env.example) and [`docs/06-IPD-Implementation-Plan.md`](./docs/06-IPD-Implementation-Plan.md)).
3. **Deploy Commands:**
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```
4. **Firebase Authorized Domains:** Add your `*.laravel.cloud` domain under **Firebase Console → Authentication → Settings → Authorized domains**.
