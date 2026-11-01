# 6. Implementation Plan Documentation (IPD)
**Project Name:** Netflix Household & Split-Billing Portal ("Netflix Household Hub")  
**Document Version:** 1.0

---

## 1. Implementation Roadmap & Current Status Overview

The project is structured into **6 execution phases**. Phases 1 through 5 are **implemented and verified** in the codebase; Phase 6 covers **production cutover on Laravel Cloud + TiDB Cloud Serverless**.

```mermaid
flowchart LR
    P1["Phase 1: Done<br/>Database & Seeders"] --> P2["Phase 2: Done<br/>Firebase Auth & Whitelist"]
    P2 --> P3["Phase 3: Done<br/>IMAP, Vault & Billing"]
    P3 --> P4["Phase 4: Done<br/>Bento UI & Alpine.js"]
    P4 --> P5["Phase 5: Done<br/>Automated Testing & Polish"]
    P5 --> P6["Phase 6: Target<br/>Laravel Cloud + TiDB Cloud Launch"]
```

| Phase | Deliverable Scope | Key Files | Status |
| :--- | :--- | :--- | :--- |
| **Phase 1** | Migrations, Eloquent Models (`encrypted` casts), 6-Month Seeder | `database/migrations/*`, `app/Models/*`, [`DatabaseSeeder.php`](file:///c:/laragon/www/netflix-automation/database/seeders/DatabaseSeeder.php) | Completed |
| **Phase 2** | Firebase Google Auth (`arise-26`) + Whitelist, Signed Magic Link, Demo Switcher | [`GoogleAuthController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/GoogleAuthController.php), [`login.blade.php`](file:///c:/laragon/www/netflix-automation/resources/views/auth/login.blade.php) | Completed |
| **Phase 3** | IMAP Parser (`ext-imap` + Pure-PHP SSL Socket), Step-Up OTP Vault, DuitNow Receipts | [`NetflixImapService.php`](file:///c:/laragon/www/netflix-automation/app/Services/NetflixImapService.php), [`VaultController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/VaultController.php), [`PaymentController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/PaymentController.php), [`AdminController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/AdminController.php), [`routes/console.php`](file:///c:/laragon/www/netflix-automation/routes/console.php) | Completed |
| **Phase 4** | Dark-Mode 12-Col Bento Dashboard, Modals, Admin Drawer, Live Timers | [`dashboard.blade.php`](file:///c:/laragon/www/netflix-automation/resources/views/dashboard.blade.php), [`DashboardController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/DashboardController.php) | Completed |
| **Phase 5** | Feature & Unit Test Suite (PHPUnit — 8 Tests, 36 Assertions Passing) | [`HouseholdPortalTest.php`](file:///c:/laragon/www/netflix-automation/tests/Feature/HouseholdPortalTest.php), [`ExampleTest.php`](file:///c:/laragon/www/netflix-automation/tests/Feature/ExampleTest.php) | Completed |
| **Phase 6** | TiDB Cloud Serverless Provisioning & Laravel Cloud Production Cutover | [`config/database.php`](file:///c:/laragon/www/netflix-automation/config/database.php), `.env` | Ready for Cutover |

---

## 2. Completed Implementation Breakdown (Phases 1–4)

### Phase 1: Foundation, Schema & Seeders
* Created 5 domain tables (`users`, `account_settings`, `billing_cycles`, `payments`, `audit_logs`) plus Laravel session/cache/job tables.
* Configured `AES-256-CBC` `'encrypted'` casts on [`AccountSetting::$netflix_password`](file:///c:/laragon/www/netflix-automation/app/Models/AccountSetting.php#L18) and [`User::$netflix_profile_pin`](file:///c:/laragon/www/netflix-automation/app/Models/User.php#L24).
* Built [`DatabaseSeeder.php`](file:///c:/laragon/www/netflix-automation/database/seeders/DatabaseSeeder.php) to seed:
  * 1 Admin (`Firdaus`) + 4 Household Members (`Alex Tan` with RM 22.00 advance credit, `Sarah Lim` in `verifying` state with SVG demo receipt, `Amirul Hakim` in `pending` state, `Chloe Wong` in `overdue` state).
  * 5 historical settled billing cycles + 1 active current month billing cycle.

### Phase 2: Authentication & Whitelist Security Layer
* Implemented [`GoogleAuthController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/GoogleAuthController.php) supporting:
  * Google OAuth 2.0 callback with case-insensitive `users.email` whitelist check and `is_active` validation (`403 Forbidden` on mismatch).
  * Passwordless 15-minute signed Magic Link (`URL::temporarySignedRoute`) with inline preview banner when `APP_DEBUG=true`.
  * Instant Demo Household Switcher (`POST /auth/demo-switch/{user}`) restricted to `local`/`debug` environments.

### Phase 3: Core Services, Vault Step-Up & Split-Billing
* Built [`NetflixImapService.php`](file:///c:/laragon/www/netflix-automation/app/Services/NetflixImapService.php) with read-only SSL IMAP polling (`OP_READONLY`), Quoted-Printable decoding, `nftoken` URL extraction, 4-digit code regex parsing, and automatic demo fallback.
* Built [`VaultController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/VaultController.php) enforcing `HTTP 402 Payment Required` for overdue members, one-time 24-hour emergency grace bypass (`grace_bypass_used_at`), 6-digit OTP email step-up (`vault_otp_{id}`), and 15-minute verified session caching.
* Built [`PaymentController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/PaymentController.php) and [`AdminController.php`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/AdminController.php) for receipt upload, SVG demo receipt rendering, receipt approval/rejection, vault password rotation, and member credit management.
* Registered `billing:generate-cycle` in [`routes/console.php`](file:///c:/laragon/www/netflix-automation/routes/console.php) with automatic advance credit deduction and monthly scheduling (`monthlyOn(1, '00:05')`).

### Phase 4: Dark-Mode Bento Grid UI & Alpine.js Client
* Built [`resources/views/auth/login.blade.php`](file:///c:/laragon/www/netflix-automation/resources/views/auth/login.blade.php) and [`resources/views/dashboard.blade.php`](file:///c:/laragon/www/netflix-automation/resources/views/dashboard.blade.php) featuring live `MM:SS` verification countdowns, 15-second password auto-remasking, DuitNow QR modal, 6-month historical ledger tabs, and a 4-tab Admin slide-over drawer.

---

## 3. Phase 5 Execution Plan: Automated Testing & Quality Assurance

To guarantee zero regressions when modifying billing rules, encryption casts, or IMAP regexes, implement the following **PHPUnit Feature & Unit Test Suite**:

### 3.1 Unit Tests (`tests/Unit/NetflixImapServiceTest.php`)
1. `test_parses_travel_verify_nftoken_url_and_4_digit_code_from_html()`:
   * Pass sample Quoted-Printable Netflix HTML containing `https://www.netflix.com/account/travel/verify?nftoken=...&amp;messageGuid=...` and `<strong>8492</strong>`.
   * Assert `approval_url` has unescaped `&`, `code === '8492'`, `profile_name === 'Alex'`, and `device_type` matches the TV string.
2. `test_parses_update_primary_location_url_without_4_digit_code()`:
   * Pass sample Household update HTML and assert `approval_url` is extracted while calendar years (e.g., `2026`) are not misidentified as 4-digit codes.

### 3.2 Feature Tests (`tests/Feature/HouseholdPortalTest.php`)
1. **Authentication & Whitelist Gate:**
   * `test_magic_link_rejects_non_whitelisted_email_with_error()`
   * `test_valid_signed_magic_link_authenticates_whitelisted_member()`
2. **Payment-Gated Vault & Step-Up OTP:**
   * `test_overdue_member_receives_402_when_requesting_vault_otp()`
   * `test_overdue_member_can_activate_24h_grace_bypass_once_and_request_otp()`
   * `test_paid_member_can_request_otp_and_reveal_encrypted_master_password()`
   * `test_invalid_otp_returns_422_and_does_not_reveal_password()`
3. **Split-Billing, Receipt Upload & Admin Verification:**
   * `test_member_can_upload_receipt_and_transition_status_to_verifying()`
   * `test_non_admin_cannot_access_admin_verification_or_settings_routes()`
   * `test_admin_can_approve_receipt_and_unlock_member_vault()`
   * `test_billing_generate_cycle_command_auto_deducts_advance_credit_balance()`

### 3.3 Running the Test Suite
```powershell
php artisan test
```

---

## 4. Phase 6 Execution Plan: Production Deployment on Laravel Cloud + TiDB Cloud

### Step 1: Create Your TiDB Cloud Serverless Cluster
1. Log into [TiDB Cloud Console](https://tidbcloud.com/) and create a free **Serverless** cluster (choose the Singapore `ap-southeast-1` region for lowest latency in Malaysia).
2. Click **Connect** on your TiDB cluster:
   * Under **Connect With**, select **Laravel** (or General MySQL).
   * Click **Generate Password** and copy the credentials (`Host`, `Port: 4000`, `User`, `Password`).
   * Create a database named `netflix_portal` via the TiDB SQL Editor (`CREATE DATABASE IF NOT EXISTS netflix_portal;`).
3. Note: [`config/database.php`](file:///c:/laragon/www/netflix-automation/config/database.php) has already been pre-configured for TiDB Cloud:
   * Defaults to `utf8mb4_unicode_ci` collation (fully compatible with all TiDB Serverless clusters).
   * Automatically resolves `/etc/ssl/certs/ca-certificates.crt` for TLS verification when `DB_HOST` contains `tidbcloud.com`.

### Step 2: Deploy the Repository to Laravel Cloud
1. Push this repository to GitHub and connect it to [Laravel Cloud](https://cloud.laravel.com/).
2. In your **Laravel Cloud Environment Settings → Environment Variables**, paste the following configuration:
   ```ini
   APP_NAME="Netflix Household Hub"
   APP_ENV=production
   APP_DEBUG=false
   APP_TIMEZONE=Asia/Kuala_Lumpur

   # TiDB Cloud Serverless Connection
   DB_CONNECTION=mysql
   DB_HOST=gateway01.ap-southeast-1.prod.aws.tidbcloud.com
   DB_PORT=4000
   DB_DATABASE=netflix_portal
   DB_USERNAME=xxxx.root
   DB_PASSWORD=your_tidb_password
   DB_COLLATION=utf8mb4_unicode_ci
   MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

   # Stateless Cloud Session & Cache (Stored in TiDB)
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database

   # Firebase Authentication (arise-26)
   FIREBASE_API_KEY="AIzaSyA_BwqDRzrU37fZXzVbQ02wHK8ugrD1Eq4"
   FIREBASE_AUTH_DOMAIN="arise-26.firebaseapp.com"
   FIREBASE_PROJECT_ID="arise-26"
   FIREBASE_STORAGE_BUCKET="arise-26.firebasestorage.app"
   FIREBASE_MESSAGING_SENDER_ID="166673631179"
   FIREBASE_APP_ID="1:166673631179:web:ea61f8df46954dd388d84e"
   FIREBASE_MEASUREMENT_ID="G-TEMJT4GVCP"

   # Dedicated IMAP Inbox for Netflix Verification Emails
   NETFLIX_IMAP_HOST=imap.gmail.com
   NETFLIX_IMAP_PORT=993
   NETFLIX_IMAP_ENCRYPTION=ssl
   NETFLIX_IMAP_USER=your-dedicated-netflix-inbox@gmail.com
   NETFLIX_IMAP_PASS=your-16-char-google-app-password
   ```
3. In **Laravel Cloud → Deploy Commands**, set the deploy script to run migrations and seed the initial 5-member records on first deploy:
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```
   *(Because `DatabaseSeeder` uses `updateOrCreate`, re-running it is safe, or you can remove `php artisan db:seed --force` after the initial deploy once you customize your member emails.)*

### Step 3: Authorize Your Laravel Cloud Domain in Firebase (`arise-26`)
1. Copy your live Laravel Cloud URL (e.g., `your-app.laravel.cloud` or your custom domain).
2. Open [Firebase Console → Authentication → Settings → Authorized domains](https://console.firebase.google.com/project/arise-26/authentication/settings).
3. Click **Add domain** and add your Laravel Cloud domain so the Google Sign-In popup is allowed in production.

### Step 4: Enable Laravel Cloud Scheduler & Object Storage (Optional)
1. **Scheduler:** In **Laravel Cloud → Processes / Scheduler**, toggle **Enable Scheduler ON**. Laravel Cloud will automatically run `php artisan schedule:run`, triggering `billing:generate-cycle` on the 1st of every month at `00:05`.
2. **Object Storage (Optional):** If you attach a **Laravel Cloud Object Storage (S3/R2)** bucket to persist uploaded receipt files across container deploys, set `FILESYSTEM_DISK=s3` in your environment variables — [`PaymentController`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/PaymentController.php) automatically uses `config('filesystems.default')`.

