# 1. Product Requirement Documentation (PRD)
**Project Name:** Netflix Household & Split-Billing Portal ("Netflix Household Hub")  
**Document Version:** 1.1  
**Status:** Baseline Implemented / Production-Ready Specification  
**Target Platform:** Laravel Cloud + TiDB Cloud Serverless (with Firebase Authentication)

---

## 1. Executive Summary & Product Vision
Managing a shared **Netflix Premium 4K** household subscription across 5 members introduces constant operational friction for the subscription leader (Admin) and household members alike:
1. **TV & Device Lockouts:** Netflix periodically challenges Smart TVs and mobile devices with 15-minute *Household Verification* or *Temporary Access Code* emails sent exclusively to the account owner's primary email address.
2. **Unsafe Credential Sharing:** Sharing the master Netflix password and individual 4-digit Profile Lock PINs over chat apps (WhatsApp/Telegram) leaves credentials exposed in plaintext and allows unpaid members to continue using the account after password rotations.
3. **Manual Split-Billing Tracking:** Collecting RM 11.00/month from 4 other members via DuitNow QR or bank transfer, tracking advance multi-month credits, and chasing receipts manually in group chats is tedious and error-prone.

**Product Vision:**  
Provide a private, self-hosted, cinema-grade web portal exclusively for the 5 whitelisted household members. Members can self-serve live 15-minute TV verification links/codes on demand, reveal the AES-256 encrypted master password behind a step-up Email OTP and active payment check, and settle their monthly split-billing share via DuitNow QR with transparent receipt verification.

---

## 2. Problem Statement & Core Value Propositions

| Pain Point | Traditional Workaround | Portal Solution |
| :--- | :--- | :--- |
| **15-Minute TV Verification Expiry** | Member texts the Admin on WhatsApp; Admin sees it 20 minutes later after the Netflix link has already expired. | **On-Demand IMAP Code Fetcher:** Members click one button to parse Netflix verification emails received in the last 15 minutes from an isolated cPanel mailbox. |
| **Plaintext Password Sprawl & Free-Riding** | Master password is pinned in a group chat; overdue members still have full access unless the Admin rotates the password and messages everyone individually. | **Payment-Gated Encrypted Vault:** Master password is encrypted at rest (`AES-256-CBC`), gated by current-month payment status (`402 Payment Required`), and protected by a 6-digit Email OTP step-up check. |
| **Opaque Monthly Split-Billing** | Spreadsheet or mental tracking of who paid this month, who paid 3 months in advance, and which bank receipt belongs to whom. | **Automated Split-Billing & DuitNow Ledger:** Monthly billing cycles auto-deduct advance credit balances, accept DuitNow receipt uploads, and give all 5 members a transparent 6-month ledger. |

---

## 3. Target User Personas

### 3.1 Persona A: Subscription Leader (`role = admin`)
* **Profile:** Firdaus (Account Owner paying RM 55.00/month for Netflix Premium 4K UHD).
* **Goals:**
  * Eliminate late-night texts asking for Netflix TV verification codes.
  * Protect personal Gmail inbox privacy (never expose personal emails to household members).
  * Rotate the master Netflix password anytime and have it immediately available only to paid members.
  * Review and approve uploaded DuitNow receipts in one click.

### 3.2 Persona B: Household Member (`role = member`)
* **Profile:** Alex, Sarah, Amirul, Chloe (4 whitelisted household members paying RM 11.00/month each).
* **Goals:**
  * Approve a Smart TV "Update Netflix Household" or "Temporary Access Code" prompt within 30 seconds without waiting for the Admin.
  * View their personal 4-digit Netflix Profile Lock PIN and copy the master password when signing in on a new device.
  * Scan the Admin's DuitNow QR code, upload their receipt, or see their remaining advance credit balance.

---

## 4. Functional Requirements

### 4.1 Authentication & Access Control (FR-AUTH)
* **FR-AUTH-01 (Strict 5-Member Whitelist):** The application must not allow public registration. Only users whose email exists in the `users` table with `is_active = true` may sign in.
* **FR-AUTH-02 (Google OAuth 2.0 Login):** Users can sign in with one click via Google OAuth (`laravel/socialite`). Unrecognized emails receive a `403 Forbidden` response and generate an `auth.login_failed` audit log.
* **FR-AUTH-03 (Passwordless Signed Magic Link):** As a fallback when Google OAuth is unavailable, whitelisted members can request a 15-minute cryptographically signed login URL sent to their email.
* **FR-AUTH-04 (Local Demo Switcher):** When running in `local` or `APP_DEBUG=true` mode, a quick profile switcher allows instant testing across all 5 seeded household personas (Paid Admin, Paid Member with Credit, Verifying Member, Pending Member, Overdue Member).

### 4.2 On-Demand TV Verification Code Fetcher (FR-IMAP)
* **FR-IMAP-01 (Email Isolation Architecture):** Only Netflix verification emails auto-forwarded from the Admin's Gmail to a dedicated cPanel mailbox (`netflix@yourdomain.com`) are accessed by the portal.
* **FR-IMAP-02 (15-Minute Live Window):** Fetching codes queries the IMAP inbox for emails from `info@account.netflix.com` received within the last 15 minutes (`900` seconds).
* **FR-IMAP-03 (HTML Parsing & Extraction):** The parser extracts the requesting **Profile Name**, **Device Type** (e.g., *Samsung QLED 4K Smart TV*), **4-Digit Verification Code** (if present), **Netflix Approval URL** (`travel/verify` or `update-primary-location` with `nftoken`), and calculates remaining seconds before expiration.
* **FR-IMAP-04 (Demo Fallback Mode):** If IMAP credentials are not configured in `.env` or `ext-imap` is unavailable locally, the service automatically returns realistic simulated verification cards so the UI can be tested end-to-end.

### 4.3 Encrypted Credentials Vault & Step-Up Security (FR-VAULT)
* **FR-VAULT-01 (Masked Display & Profile PIN):** The dashboard displays the master Netflix email, a masked master password (`••••••••••••`), the time since last password rotation, and the logged-in member's personal Netflix Profile Name and 4-digit Profile Lock PIN.
* **FR-VAULT-02 (Payment Gating - HTTP 402):** If a member's payment for the current billing cycle is marked `overdue` (and no active grace bypass exists), attempting to request a Vault OTP or reveal the password returns `402 Payment Required`.
* **FR-VAULT-03 (One-Time 24-Hour Emergency Grace Bypass):** An overdue member may activate a one-time 24-hour emergency grace bypass (usable once every 30 days) to unlock the vault while they arrange their bank transfer.
* **FR-VAULT-04 (6-Digit Step-Up Email OTP):** Eligible members must verify a 6-digit OTP sent to their email (valid for 5 minutes) before the backend decrypts and returns the plaintext master password.
* **FR-VAULT-05 (15-Minute Step-Up Session & 15-Second Auto-Remask):** Once verified, the session remembers step-up verification for 15 minutes (`vault_verified_until`), while the frontend UI automatically re-masks the plaintext password 15 seconds after reveal.

### 4.4 Shared Split-Billing & DuitNow Ledger (FR-BILL)
* **FR-BILL-01 (Automated Monthly Cycle Generation):** An Artisan command (`php artisan billing:generate-cycle {month?}`) scheduled on the 1st of every month at `00:05` creates the monthly `BillingCycle` (`YYYY-MM`) and initializes 5 member `Payment` records.
* **FR-BILL-02 (Advance Credit Auto-Deduction):** During cycle generation, if a member's `credit_balance` is greater than or equal to their `monthly_share_amount`, the system automatically decrements `credit_balance` and marks their payment as `paid`.
* **FR-BILL-03 (DuitNow QR & Bank Transfer Modal):** Members can view the Admin's DuitNow QR code image, bank name, account holder name, and one-click copyable bank account number.
* **FR-BILL-04 (Receipt Upload & Private Storage):** Members can upload transfer receipts (`JPG`, `PNG`, `PDF` up to 4 MB) with an optional reference note. Uploading transitions the payment status to `verifying` and notifies the Admin via email.
* **FR-BILL-05 (Transparent 5-Member Matrix & 6-Month History):** All household members can view the current month's 5-member payment status matrix (`PAID`, `VERIFYING`, `PENDING`, `OVERDUE`) and switch tabs to inspect up to 5 previous billing cycles.

### 4.5 Admin Control Plane (FR-ADMIN)
* **FR-ADMIN-01 (Receipt Verification Queue):** Admins can preview uploaded receipts (images/PDFs) and click **Approve** (marks `paid`, records `verified_by` and `verified_at`) or **Reject** (marks `overdue` and prompts member to re-upload).
* **FR-ADMIN-02 (Master Vault & DuitNow Settings):** Admins can rotate the master Netflix password (updating `password_updated_at`), update plan costs, upload a new DuitNow QR image, and edit bank details.
* **FR-ADMIN-03 (Household Member & Credit Management):** Admins can update any of the 5 members' names, whitelisted Google emails, Netflix profile names, 4-digit Profile Lock PINs, monthly share amounts, advance credit balances, and active status.
* **FR-ADMIN-04 (Security Audit Trail):** Admins can inspect the latest security and operational events (`vault.password_revealed`, `imap.code_fetched`, `admin.payment_verified`, `auth.login_failed`, etc.) with IP addresses and timestamps.

---

## 5. Non-Functional Requirements (NFR)
* **NFR-01 (Data Encryption at Rest):** `account_settings.netflix_password` and `users.netflix_profile_pin` must be encrypted using Laravel's `AES-256-CBC` (`encrypted` cast) tied to `APP_KEY`.
* **NFR-02 (Rate Limiting & Abuse Protection):**
  * Magic Link requests: max `5` per minute (`throttle:5,1`).
  * IMAP code fetches: max `10` per minute (`throttle:10,1`).
  * Vault OTP requests: max `3` per minute (`throttle:3,1`).
  * Vault OTP reveal attempts: max `6` per minute (`throttle:6,1`).
* **NFR-03 (Cloud-Native PaaS & Distributed SQL Compatibility):** Must run seamlessly on **Laravel Cloud** (stateless PHP 8.2+ compute, native Background Scheduler, and S3/R2 Object Storage support) backed by **TiDB Cloud Serverless** (MySQL-compatible distributed SQL over TLS port `4000`).
* **NFR-04 (Responsive & Accessible UI):** Mobile-first responsive Bento Grid that works seamlessly on phones (when standing in front of a Smart TV) and desktops, adhering to WCAG 2.2 AA contrast and keyboard focus standards.

---

## 6. Success Metrics
1. **Time-to-Verify TV Prompt:** Under **15 seconds** from opening the portal to clicking the parsed Netflix approval link or reading the 4-digit code.
2. **Zero Admin Interruptions:** **100%** of routine TV household verification requests handled via self-service without contacting the Admin.
3. **On-Time Monthly Collection:** **100%** visibility into monthly split-billing settlement by the 5th of each month.

---

## 7. Future Roadmap (Post-v1.0)
* **Phase 2.1 — Telegram / WhatsApp Webhook Alerts:** Instant push notifications to the Admin when a receipt is uploaded and to members when their monthly bill is generated.
* **Phase 2.2 — Automated Receipt OCR:** Extract transfer amount, reference ID, and date from uploaded DuitNow receipts automatically.
* **Phase 2.3 — Multi-Service Household Support:** Extend split-billing and vault cards to YouTube Premium, Spotify Family, or Apple One.

