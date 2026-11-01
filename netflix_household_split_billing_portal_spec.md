# Project Specification: Netflix Household & Split-Billing Portal

## 1. Project Overview & Goal
Build a self-hosted **Laravel 11+ Web Application** (designed for cPanel / MySQL hosting) that manages a 5-member shared Netflix household subscription. 

The system solves three core problems for the subscription leader (Admin) and the 5 household members:
1. **On-Demand Household Verification Code Fetcher:** Connects via IMAP to a dedicated cPanel email account (`netflix@yourdomain.com` receiving auto-forwarded Netflix verification emails from Gmail) to parse and display 15-minute Netflix household verification codes or `nftoken` approval links on demand.
2. **Encrypted Master Credentials Vault (with Step-Up Auth & Payment Gating):** Stores the master Netflix email, AES-256 encrypted master password, and individual 4-digit Profile Lock PINs. Only members whose current monthly share is marked as `Paid` can reveal or copy the master password after passing a 6-digit Email OTP step-up verification.
3. **Shared Split-Billing & Payment Ledger:** Tracks monthly Netflix billing cycles, calculates each member's split share, displays the leader's DuitNow QR code / bank details, accepts payment receipt uploads from members, and provides an Admin verification workflow.

---

## 2. Tech Stack & Packages
* **Framework:** Laravel 11.x (PHP 8.2+)
* **Database:** MySQL / MariaDB
* **Frontend:** Blade Templates + Tailwind CSS (Dark-mode Netflix-inspired Bento Grid UI) + Alpine.js for reactive UI modals, timers, and clipboard actions
* **Authentication:** 
  * Primary Login: `laravel/socialite` (Google OAuth 2.0 with strict 5-email database whitelist; no public registration)
  * Fallback Login: Passwordless Signed Magic Link via cPanel SMTP (`URL::temporarySignedRoute`)
  * Vault Step-Up Auth: 6-digit Email OTP (valid for 5 minutes) required before decrypting the master Netflix password
* **IMAP Integration:** PHP native `imap_open` or `webklex/laravel-imap` (connecting to local cPanel mail server on port `993` with SSL)

---

## 3. System Architecture & End-to-End Flows

### Flow A: Email Isolation & On-Demand IMAP Verification Code Fetching
1. A member gets prompted on their TV/phone for a Netflix Household Verification or Temporary Access Code and clicks **"Send Email"**.
2. Netflix sends the verification email to the Admin's primary Gmail.
3. A Gmail filter (`from:info@account.netflix.com ("temporary access code" OR "Netflix Household" OR "update-primary-location")`) auto-forwards ONLY verification emails to `netflix@yourdomain.com` (hosted on cPanel).
4. The member logs into the Laravel Portal via Google OAuth and clicks **"Fetch Latest Code"**.
5. Laravel connects to `mail.yourdomain.com:993/imap/ssl` and fetches emails received within the last **15 minutes**.
6. An HTML Parser service extracts:
   * Requesting **Profile Name** & **Device Type** (e.g., *Samsung Smart TV*)
   * Email **Timestamp** and remaining time before 15-minute expiry
   * The **Approval Link** (`https://www.netflix.com/account/travel/verify?nftoken=...` or `.../update-primary-location?nftoken=...`) or 4-digit code.
7. The portal displays the parsed request cards so the member can tap **"Approve / View 4-Digit Code"** directly.

### Flow B: Google OAuth Whitelist & Step-Up Password Vault
1. **Strict Whitelist Login:** Users click **"Continue with Google"**. `GoogleAuthController` checks if `$googleUser->getEmail()` exists in the `users` table and `is_active === true`. Unrecognized emails receive a `403 Forbidden` response.
2. **Payment Gating:** On the dashboard, the master Netflix password is masked (`••••••••••••`). If the user has an `overdue` payment for the current billing cycle, clicking **[Reveal]** returns a `402 Payment Required` prompt instructing them to upload their transfer receipt first (unless they trigger a one-time 24-hour emergency grace bypass).
3. **Step-Up OTP Verification:** When a paid member clicks **[Reveal Master Password]**, the system sends a 6-digit OTP to their email (or prompts if not verified in the last 15 minutes). Upon OTP confirmation, the backend decrypts `netflix_password` from `account_settings`, logs the event in `audit_logs`, returns the plaintext password over JSON, and re-masks it on the frontend after 15 seconds.

### Flow C: Split-Billing & DuitNow Receipt Verification
1. Monthly billing cycles are created manually by the Admin or automatically on the 1st of the month via Laravel Scheduler (`php artisan billing:generate-cycle`).
2. Each active member gets a `payments` record initialized as `pending` (or automatically marked `paid` if their `credit_balance` covers the monthly share).
3. Members view the Admin's **DuitNow QR image** and bank account number on the dashboard, make their transfer, and upload an image/PDF receipt (`storage/app/private/receipts`).
4. The status changes to `verifying` and notifies the Admin via email/Telegram.
5. The Admin clicks **[Approve]** in the Admin Panel, updating the status to `paid` and unlocking full vault privileges.

---

## 4. Database Schema (Migrations)

### 4.1 `users` (Household Members & Admin)
* `id` (bigint, PK)
* `name` (string)
* `email` (string, unique) — *Pre-seeded whitelist of 5 members + 1 admin*
* `google_id` (string, nullable, index)
* `avatar` (string, nullable)
* `role` (enum: `admin`, `member`, default: `member`)
* `netflix_profile_name` (string) — *e.g., "Alex", "Sarah"*
* `netflix_profile_pin` (string, encrypted, nullable) — *4-digit Netflix Profile Lock PIN*
* `monthly_share_amount` (decimal 8,2, default: 11.00)
* `credit_balance` (decimal 8,2, default: 0.00) — *For members who pay multiple months in advance*
* `grace_bypass_used_at` (timestamp, nullable)
* `is_active` (boolean, default: true)
* `last_login_at` (timestamp, nullable)
* `last_login_ip` (string, nullable)
* `timestamps`

### 4.2 `account_settings` (Single-Row Configuration & Encrypted Vault)
* `id` (bigint, PK)
* `netflix_email` (string)
* `netflix_password` (text) — *Cast as `encrypted` in Eloquent Model*
* `password_updated_at` (timestamp)
* `plan_name` (string, default: 'Netflix Premium 4K')
* `total_monthly_cost` (decimal 8,2)
* `billing_day_of_month` (tinyInteger, default: 1)
* `bank_name` (string, nullable)
* `bank_account_number` (string, nullable)
* `bank_account_holder` (string, nullable)
* `duitnow_qr_path` (string, nullable)
* `timestamps`

### 4.3 `billing_cycles`
* `id` (bigint, PK)
* `cycle_month` (string, unique) — *Format: `YYYY-MM`, e.g., `2026-10`*
* `total_cost` (decimal 8,2)
* `due_date` (date)
* `notes` (text, nullable)
* `timestamps`

### 4.4 `payments`
* `id` (bigint, PK)
* `user_id` (foreignId -> `users.id`, cascade)
* `billing_cycle_id` (foreignId -> `billing_cycles.id`, cascade)
* `amount` (decimal 8,2)
* `status` (enum: `pending`, `verifying`, `paid`, `overdue`, default: `pending`)
* `receipt_path` (string, nullable)
* `reference_note` (string, nullable)
* `paid_at` (timestamp, nullable)
* `verified_at` (timestamp, nullable)
* `verified_by` (foreignId -> `users.id`, nullable)
* `timestamps`

### 4.5 `audit_logs`
* `id` (bigint, PK)
* `user_id` (foreignId -> `users.id`, nullable)
* `action` (string) — *e.g., `vault.password_revealed`, `imap.code_fetched`, `auth.login_failed`*
* `metadata` (json, nullable)
* `ip_address` (string, nullable)
* `user_agent` (string, nullable)
* `created_at` (timestamp)

---

## 5. Key Backend Services & Controllers

### 5.1 `App\Services\NetflixImapService`
* Connects to IMAP using credentials in `.env` (`NETFLIX_IMAP_HOST`, `NETFLIX_IMAP_PORT=993`, `NETFLIX_IMAP_USER`, `NETFLIX_IMAP_PASS`).
* Searches messages `SINCE` today from `info@account.netflix.com`.
* Filters messages received within the last 15 minutes (`now()->subMinutes(15)`).
* Parses HTML body using `DOMDocument` / Regex to extract:
  * Approval URL matching `/https:\/\/www\.netflix\.com\/account\/(travel\/verify|update-primary-location)[^\s"']+/i` (unescaping `&amp;` to `&`).
  * Any standalone 4-digit verification code if present in the template.
  * Requesting profile name and device string from the Netflix email table.
* Returns a clean array of DTOs to the controller.

### 5.2 `App\Http\Controllers\VaultController`
* `POST /vault/request-otp`: Rate-limited (`throttle:3,1`). Checks if the authenticated user's current cycle payment status is not `overdue` (or if `grace_bypass` is active). Generates a 6-digit code in `Cache::put("vault_otp_{$user->id}", $code, now()->addMinutes(5))` and sends it via mail.
* `POST /vault/reveal`: Validates the 6-digit OTP, logs `vault.password_revealed` in `audit_logs`, and returns:
  ```json
  {
    "email": "leader@gmail.com",
    "password": "DecryptedPlaintextPassword",
    "profile_name": "Alex",
    "profile_pin": "4829",
    "updated_human": "3 days ago"
  }
  ```

### 5.3 `App\Http\Controllers\PaymentController`
* `POST /payments/{payment}/upload-receipt`: Validates file upload (`mimes:jpg,jpeg,png,pdf|max:4096`), stores in private disk, updates payment status to `verifying`.
* `POST /admin/payments/{payment}/verify`: Admin-only route. Marks payment as `paid`, sets `verified_at = now()`, and logs the action.

---

## 6. UI/UX Layout (Dark-Mode Bento Grid)
Use a sleek dark theme (`bg-zinc-950`, cards `bg-zinc-900/80 border border-zinc-800`, primary accent `bg-red-600 hover:bg-red-500`, status chips in emerald/amber/rose).

1. **Top Navbar:**
   * App Logo ("Netflix Household Hub"), Current Member Badge + Avatar, Role Badge, and Logout button.
2. **Bento Card 1 (Top-Left, Span 7 cols): Live Verification Code Fetcher**
   * Prominent **"Fetch Latest Verification Email"** button with loading spinner.
   * Displays active verification requests from the last 15 minutes with a live countdown timer, Device Name, Profile Name, and a green **"Open Approval Link / View 4-Digit Code"** button.
3. **Bento Card 2 (Top-Right, Span 5 cols): Credentials & Profile Vault**
   * Displays Master Email (`[Copy]`), Master Password (`••••••••••••` + `[Reveal with OTP]` + `[Copy]`), `Last updated X ago` badge, and the logged-in member's personal **Netflix Profile Name & 4-Digit Profile Lock PIN**.
   * Shows a polite lock overlay if the current month's bill is `Overdue`.
4. **Bento Card 3 (Middle-Left, Span 5 cols): My Monthly Share & DuitNow Payment**
   * Current billing cycle status badge (`PAID`, `PENDING VERIFICATION`, `OVERDUE`).
   * Toggleable modal/drawer showing the Admin's **DuitNow QR Code**, Bank Name, copyable Bank Account Number, and a drag-and-drop **Upload Transfer Receipt** form.
5. **Bento Card 4 (Middle-Right, Span 7 cols): Household Split-Billing Ledger**
   * Transparent 5-member matrix for the current month showing who has settled their share (`Paid` in green, `Verifying` in amber, `Pending` in zinc) plus a 6-month historical tab.
6. **Admin Drawer / Tab (Visible only to `role === 'admin'`):**
   * Quick form to rotate/update the Master Netflix Password (auto-updates `password_updated_at`).
   * Pending Receipt Verification queue with side-by-side receipt preview and one-click **[Approve]** / **[Reject]** buttons.
   * Member Editor (manage the 5 whitelisted Google emails, profile names, profile PINs, and advance credit balances).

---

## 7. `.env` Variables to Include in `.env.example`
```env
# Google OAuth Whitelist Auth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

# Dedicated cPanel IMAP Mailbox for Netflix Verification Emails
NETFLIX_IMAP_HOST=mail.yourdomain.com
NETFLIX_IMAP_PORT=993
NETFLIX_IMAP_ENCRYPTION=ssl
NETFLIX_IMAP_USER=netflix@yourdomain.com
NETFLIX_IMAP_PASS=
```

---

## 8. Step-by-Step Build Instructions for AI Agent in Antigravity IDE
When generating this project, execute in the following order:
1. **Migrations & Models:** Create all 5 migrations (`users`, `account_settings`, `billing_cycles`, `payments`, `audit_logs`) and Eloquent models with encrypted casts and relationships.
2. **Database Seeder (`DatabaseSeeder.php`):** Seed the initial `AccountSetting` row, 1 Admin user, 4 Household Member users, and a sample current `BillingCycle` with 5 `Payment` records so the app works immediately out of the box.
3. **Authentication Layer:** Implement `GoogleAuthController` (with Socialite email whitelist check) + a local development/fallback Magic Link login route so it can be tested locally before Google OAuth credentials are added.
4. **Services & Controllers:** Implement `NetflixImapService` (with a mock/demo fallback mode when `NETFLIX_IMAP_HOST` is empty for local testing), `VaultController` (with Email OTP step-up verification), `PaymentController`, and `AdminController`.
5. **Blade Views:** Build the responsive dark-mode Bento Grid Dashboard (`resources/views/dashboard.blade.php`) and Admin Management Modal/Page using Tailwind CSS and Alpine.js.