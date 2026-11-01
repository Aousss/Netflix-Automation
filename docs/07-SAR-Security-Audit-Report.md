# 7. Security Audit Report (SAR)
**Project Name:** Netflix Household & Split-Billing Portal ("Netflix Household Hub")  
**Skill / Methodology:** `security-audit` (v1.0 — Source-First Boundary & Validation Workflow)  
**Run Profile:** `quick` (Single-Wave Full-Repository Pass — Partial Coverage)  
**Source Ref:** Branch `imp/system-ui` @ `cd3cd64`  
**Execution Policy:** `sandboxed-source-and-local-only` (No live or external endpoint probing)  
**Date:** 2026-11-01

---

## 1. Run Metadata & Scope Statement

| Field | Value |
| :--- | :--- |
| **Run ID** | `run-1` (`quick` profile) |
| **Target Repository** | `c:\laragon\www\netflix-automation` (`Aousss/Netflix-Automation`) |
| **Reviewed Source Ref** | `cd3cd64` (Clean worktree on `imp/system-ui`) |
| **In-Scope Paths** | `routes/*`, `app/Http/Controllers/*`, `app/Services/*`, `app/Models/*`, `app/Providers/*`, `config/*`, `database/*`, `resources/views/*` |
| **Companion Domains Applied** | `WEB-PROTOCOL-AND-AUTH.md`, `CLIENT-SIDE.md`, `DATA-ISOLATION-AND-LIFECYCLE.md`, `CLOUD-AND-DEPLOYMENT.md` |
| **Prior Runs** | None (Initial baseline audit; no carried records) |
| **Coverage Notice** | This is a **`quick` profile** pass (single hunter wave + independent source verification across `surface × boundary × attack class`). It is a bounded partial pass and does not claim exhaustive coverage of third-party vendor packages (`vendor/`). |

---

## 2. Executive Security Posture Summary

The **Netflix Household Hub** implements several strong foundational security controls:
* **At-Rest Encryption:** Both the master Netflix password ([`AccountSetting::$netflix_password`](file:///c:/laragon/www/netflix-automation/app/Models/AccountSetting.php#L18)) and member 4-digit Profile Lock PINs ([`User::$netflix_profile_pin`](file:///c:/laragon/www/netflix-automation/app/Models/User.php#L24)) use Laravel's `AES-256-CBC` `'encrypted'` cast and are excluded from serialization via `$hidden`.
* **Constant-Time OTP Comparison:** Step-up OTP verification in [`VaultController::reveal()`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/VaultController.php#L82) uses `hash_equals()` and consumes the cache token upon success.
* **Strict URL Pinning in IMAP Parser:** [`NetflixImapService::parseHtmlBody()`](file:///c:/laragon/www/netflix-automation/app/Services/NetflixImapService.php#L100) restricts extracted approval links strictly to `https://www.netflix.com/account/(travel/verify|update-primary-location)...`, preventing `javascript:` or open-redirect link injection from malicious emails.

However, the audit identified **5 Confirmed Findings** (`2 High`, `2 Medium`, `1 Low`) and **2 Needs-Validation Leads** where trust boundaries can be crossed under specific conditions. All 5 confirmed findings have small, surgical source fixes detailed in Section 4.

---

## 3. Confirmed Findings Summary Table

| ID / Fingerprint | Severity | Title | Crossed Trust Boundary | One-Line Observed Result |
| :--- | :--- | :--- | :--- | :--- |
| **`SAR-001`**<br/>`auth-demo-switch-prod-takeover` | **HIGH** | Unauthenticated Admin Takeover via `/auth/demo-switch/{user}` When Default Admin Email Exists | Unauthenticated Guest → Authenticated Admin Session | Any visitor can `POST /auth/demo-switch/1` on production if `admin@household.local` has not yet been renamed, gaining full Admin & Vault access. |
| **`SAR-002`**<br/>`app-key-public-fallback-derivation` | **HIGH** | Predictable Fallback `APP_KEY` Derived from Public `APP_NAME` and `APP_URL` | Unauthenticated Guest → Encrypted Cookie / Vault Forgery | When `APP_KEY` is unset or invalid in `.env`, the fallback key is `sha256(APP_NAME . '|' . APP_URL)`, allowing anyone who knows the public URL to compute `APP_KEY` and forge session cookies. |
| **`SAR-003`**<br/>`admin-duitnow-svg-stored-xss` | **MEDIUM** | Stored XSS via Permitted `.svg` Upload in `duitnow_qr` Public Storage | Upload File Input → Same-Origin Browser Execution | `AdminController::updateSettings` accepts `.svg` files and stores them on the `public` disk where embedded `<script>` executes on direct view. |
| **`SAR-004`**<br/>`payment-demo-receipt-svg-xss` | **MEDIUM** | Unescaped XML Interpolation in `demo-receipt` SVG Response (`PaymentController::showReceipt`) | Member `reference_note` / `name` → Same-Origin SVG Execution (`image/svg+xml`) | `$payment->reference_note` and `$payment->user->name` are interpolated raw into an `image/svg+xml` response without XML entity escaping. |
| **`SAR-005`**<br/>`vault-otp-missing-attempt-burn` | **LOW** | Step-Up Vault OTP Remains Valid Across Unlimited Failed Guesses Within 5-Minute TTL | Authenticated Member → Step-Up Vault Boundary (`/vault/reveal`) | Failed OTP checks in `VaultController::reveal` do not increment a per-OTP attempt counter or invalidate the cached OTP after N failures. |

---

## 4. Confirmed Findings — Detailed Analysis & Remediations

---

### `SAR-001` — Unauthenticated Admin Takeover via `/auth/demo-switch/{user}` When Default Admin Email Exists
* **Severity:** **HIGH** (Likelihood: High on fresh deploy | Impact: High)
* **Confidence:** High (Confirmed via source trace)
* **Source Trace:**
  1. **Entrypoint:** [`routes/web.php:28-29`](file:///c:/laragon/www/netflix-automation/routes/web.php#L28-L29) — `Route::post('/auth/demo-switch/{user}', [GoogleAuthController::class, 'demoSwitch'])`
  2. **Propagation:** [`app/Http/Controllers/GoogleAuthController.php:226-234`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/GoogleAuthController.php#L226-L234) — Checks `$hasDefaultAdmin = User::where('role', 'admin')->where('email', 'admin@household.local')->exists();`
  3. **Sink:** [`app/Http/Controllers/GoogleAuthController.php:242`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/GoogleAuthController.php#L242) — `Auth::login($user, true);`
* **Lower-Trust Principal:** Unauthenticated external HTTP client (`guest`).
* **Affected Resource / Principal:** Household Admin account (`users.id = 1`, `role = 'admin'`) and all 4 household member accounts.
* **Conditions:** Application is deployed to production (`APP_ENV=production`, `APP_DEBUG=false`), and the seeded admin record still has `email = 'admin@household.local'`.
* **Bounded Local Verification:**
  Sending `POST /auth/demo-switch/1` with a valid CSRF token when `APP_ENV=production`, `APP_DEBUG=false`, and `admin@household.local` exists in `users` bypasses the `abort(403)` guard on line 233 and authenticates the caller as User `#1` (`admin`).
* **Smallest Effective Source Fix:**
  Remove the `$hasDefaultAdmin` exception from [`GoogleAuthController::demoSwitch()`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/GoogleAuthController.php#L226-L234) so `demoSwitch` is strictly restricted to `app()->environment('local')` (or an explicit `env('ALLOW_DEMO_SWITCH', false)` boolean), and require production deployments to set `ADMIN_EMAIL=your.real.email@gmail.com` in `.env`:
  ```php
  public function demoSwitch(Request $request, User $user): RedirectResponse
  {
      if (! app()->environment('local')) {
          abort(403);
      }
      // ...
  }
  ```

---

### `SAR-002` — Predictable Fallback `APP_KEY` Derived from Public `APP_NAME` and `APP_URL`
* **Severity:** **HIGH** (Likelihood: Medium — requires missing/invalid `APP_KEY` | Impact: Critical — session/encryption forgery)
* **Confidence:** High (Confirmed via source trace)
* **Source Trace:**
  1. **Entrypoint:** [`app/Providers/AppServiceProvider.php:17-28`](file:///c:/laragon/www/netflix-automation/app/Providers/AppServiceProvider.php#L17-L28)
  2. **Sink:** `config(['app.key' => 'base64:' . base64_encode($fallbackBytes)]);` where `$fallbackBytes = hash('sha256', config('app.name', 'netflix-hub') . '|' . config('app.url', 'cloud'), true);`
* **Lower-Trust Principal:** Unauthenticated external actor.
* **Affected Resource / Principal:** Laravel `Encrypter` (`EncryptCookies` middleware, `SESSION_DRIVER=cookie` payloads, signed Magic Link URLs, and encrypted model casts).
* **Conditions:** Operator deploys the application without a valid 32-byte `APP_KEY` in `.env` (or leaves `APP_KEY=base64:...`).
* **Observed Result:** Because `APP_NAME` (`Netflix Household Hub`) and `APP_URL` (the public website URL) are publicly observable, an external actor can deterministically compute the exact 32-byte fallback `APP_KEY`, sign arbitrary `URL::temporarySignedRoute` Magic Links (`/auth/magic-link/verify/1`), or forge encrypted session cookies.
* **Smallest Effective Source Fix:**
  In [`AppServiceProvider::register()`](file:///c:/laragon/www/netflix-automation/app/Providers/AppServiceProvider.php#L17-L28), mix non-public deployment secrets (`DB_PASSWORD`, `DB_USERNAME`, `DB_HOST`, `__FILE__`) into the fallback hash, or restrict fallback key generation to non-production environments:
  ```php
  if (! is_string($decoded) || strlen($decoded) !== 32) {
      $secretEntropy = implode('|', [
          config('app.name'),
          config('database.connections.mysql.host'),
          config('database.connections.mysql.username'),
          config('database.connections.mysql.password'),
          __FILE__,
      ]);
      $fallbackBytes = hash('sha256', $secretEntropy, true);
      config(['app.key' => 'base64:' . base64_encode($fallbackBytes)]);
  }
  ```

---

### `SAR-003` — Stored XSS via Permitted `.svg` Upload in `duitnow_qr` Public Storage
* **Severity:** **MEDIUM** (Likelihood: Low | Impact: Medium — Same-Origin JavaScript execution)
* **Confidence:** High (Confirmed via source trace)
* **Source Trace:**
  1. **Entrypoint:** [`app/Http/Controllers/AdminController.php:102`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/AdminController.php#L102) — `'duitnow_qr' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:4096']`
  2. **Sink:** [`app/Http/Controllers/AdminController.php:123`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/AdminController.php#L123) — `$request->file('duitnow_qr')->store('duitnow', 'public');`
* **Lower-Trust Principal:** Compromised admin session or social-engineered admin uploading a downloaded `.svg` file.
* **Affected Resource / Principal:** Any household member who opens the stored SVG URL (`/storage/duitnow/<hash>.svg`) directly in their browser.
* **Smallest Effective Source Fix:**
  Remove `svg` from the allowed MIME list in [`AdminController::updateSettings()`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/AdminController.php#L102):
  ```php
  'duitnow_qr' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
  ```
  And update `accept=".jpg,.jpeg,.png,.webp"` in [`resources/views/dashboard.blade.php`](file:///c:/laragon/www/netflix-automation/resources/views/dashboard.blade.php#L842).

---

### `SAR-004` — Unescaped XML Interpolation in `demo-receipt` SVG Response (`PaymentController::showReceipt`)
* **Severity:** **MEDIUM** (Likelihood: Medium | Impact: Medium — Same-Origin Stored XSS via SVG)
* **Confidence:** High (Confirmed via source trace)
* **Source Trace:**
  1. **Entrypoint:** [`app/Http/Controllers/PaymentController.php:27`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/PaymentController.php#L27) (`reference_note`) or [`AdminController.php:141`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/AdminController.php#L141) (`name`).
  2. **Propagation:** [`app/Http/Controllers/PaymentController.php:71-92`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/PaymentController.php#L71-L92) — Heredoc `<<<SVG` interpolates `{$payment->user->name}`, `{$payment->reference_note}`, and `{$payment->billingCycle->cycle_month}` without escaping.
  3. **Sink:** [`app/Http/Controllers/PaymentController.php:94`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/PaymentController.php#L94) — `return response($svg, 200, ['Content-Type' => 'image/svg+xml']);`
* **Lower-Trust Principal:** Authenticated household member or Admin viewing `/payments/{payment}/receipt` in a top-level tab (`target="_blank"`).
* **Smallest Effective Source Fix:**
  HTML/XML-escape all dynamic variables before interpolating into the SVG string and attach a strict `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'` header in [`PaymentController::showReceipt()`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/PaymentController.php#L70-L95):
  ```php
  $sender = e($payment->user?->name ?? 'Household Member');
  $ref = e($payment->reference_note ?? '—');
  $cycle = e($payment->billingCycle?->cycle_month ?? '');
  $amount = e((string) $payment->amount);
  ```

---

### `SAR-005` — Step-Up Vault OTP Remains Valid Across Failed Guesses Within 5-Minute TTL
* **Severity:** **LOW** (Likelihood: Low — mitigated by `throttle:6,1` | Impact: Medium)
* **Confidence:** High (Confirmed via source trace)
* **Source Trace:**
  1. **Entrypoint:** [`routes/web.php:44-45`](file:///c:/laragon/www/netflix-automation/routes/web.php#L44-L45) — `POST /vault/reveal` (`throttle:6,1`).
  2. **Sink:** [`app/Http/Controllers/VaultController.php:81-88`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/VaultController.php#L81-L88) — On mismatch, returns `422` without incrementing a per-OTP failure counter or invalidating `vault_otp_{$user->id}`.
* **Smallest Effective Source Fix:**
  Track failed attempts per issued OTP (`vault_otp_attempts_{$user->id}`) and invalidate `vault_otp_{$user->id}` after 5 failed attempts in [`VaultController::reveal()`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/VaultController.php#L81-L90).

---

## 5. Needs Validation (`NEEDS VALIDATION`) Table

These items depend on external cloud provider configuration (Firebase Console and Laravel Cloud ingress proxy) outside the repository source tree. Per `security-audit` rules, they carry no severity until validated.

| ID / Fingerprint | Title | Repository Trace | Exact Blocker | Safe Owner-Observed Validation Plan |
| :--- | :--- | :--- | :--- | :--- |
| **`NV-001`**<br/>`firebase-email-verified-claim` | Firebase `accounts:lookup` Response Not Checked for `emailVerified === true` | [`GoogleAuthController.php:60-70`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/GoogleAuthController.php#L60-L70) | Whether the `arise-26` Firebase project allows unverified Email/Password sign-ups alongside Google OAuth is configured in Firebase Console, not in repository source. | 1. **In Source:** Add `if (empty($fbUser['emailVerified']))` rejection in `GoogleAuthController::handleFirebaseCallback()`.<br/>2. **In Firebase Console (`arise-26`):** Verify that only **Google** provider is enabled under *Authentication → Sign-in method*. |
| **`NV-002`**<br/>`laravel-cloud-trusted-proxies` | Unconfigured `trustProxies` Middleware Behind Laravel Cloud Load Balancer | [`bootstrap/app.php:13-15`](file:///c:/laragon/www/netflix-automation/bootstrap/app.php#L13-L15) → [`routes/web.php:21,36,41,44`](file:///c:/laragon/www/netflix-automation/routes/web.php#L36) | Whether Laravel Cloud's edge proxy rewrites `REMOTE_ADDR` or requires `$middleware->trustProxies(at: '*')` to avoid shared-IP rate-limit exhaustion across users. | Sign in on the deployed Laravel Cloud instance, open **Admin → Audit Log**, and inspect the `IP` column. If it shows a private IP (`10.x` / `172.x`), add `$middleware->trustProxies(at: '*');` in `bootstrap/app.php`. |

---

## 6. Hardening Notes & Positive Security Patterns

### Positive Security Patterns Observed
1. **Strict Private Receipt Ownership Check ([`PaymentController.php:21,66`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/PaymentController.php#L21)):** Both `uploadReceipt()` and `showReceipt()` enforce `$payment->user_id !== $user->id && ! $user->isAdmin()` before reading or writing receipt files, preventing Insecure Direct Object Reference (IDOR) across household members.
2. **Session Fixation Protection ([`GoogleAuthController.php:90,141,203`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/GoogleAuthController.php#L90)):** Every authentication path (`handleFirebaseCallback`, `handleGoogleCallback`, `verifyMagicLink`, `demoSwitch`) calls `$request->session()->regenerate()`, and `logout()` calls `$request->session()->invalidate()` + `$request->session()->regenerateToken()`.
3. **Read-Only IMAP Mode ([`NetflixImapService.php:40,227`](file:///c:/laragon/www/netflix-automation/app/Services/NetflixImapService.php#L40)):** Both `imap_open` (`OP_READONLY`) and the pure-PHP socket fallback (`EXAMINE INBOX` + `BODY.PEEK`) open the mailbox in read-only mode so the web app can never delete or alter emails.

### Defense-in-Depth Hardening Notes
* **IMAP TLS Peer Verification ([`NetflixImapService.php:37,170`](file:///c:/laragon/www/netflix-automation/app/Services/NetflixImapService.php#L170)):** Consider making TLS certificate verification configurable via `NETFLIX_IMAP_VERIFY_CERT=true` when connecting to public providers like `imap.gmail.com` so the IMAP stream verifies Google's TLS certificate.
* **Uploaded Receipt Response Headers ([`PaymentController.php:102`](file:///c:/laragon/www/netflix-automation/app/Http/Controllers/PaymentController.php#L102)):** Add `'Content-Disposition' => 'inline'` and `'X-Content-Type-Options' => 'nosniff'` headers when streaming user-uploaded receipts from disk.

---

## 7. Coverage Ledger Summary (`quick` Profile)

| Surface / Subsystem Unit (`profile/quick/all-in-scope-subsystems`) | Trust Boundary | Attack Class | Status | Findings / Leads |
| :--- | :--- | :--- | :--- | :--- |
| `GoogleAuthController` (`/login`, `/auth/*`, `/logout`) | Guest ↔ Authenticated Member/Admin | Auth Bypass, OAuth/JWT Claim Binding, Session Lifecycle | `covered` | `SAR-001`, `NV-001` |
| `AppServiceProvider` & `config/database.php` | Unauthenticated HTTP ↔ Framework Crypto & DB | Key Derivation, Auto-Migration, Proxy Trust | `covered` | `SAR-002`, `NV-002` |
| `VaultController` (`/vault/*`) & `User::canAccessVault` | Authenticated Member ↔ Encrypted Master Vault | Step-Up Bypass, Payment Gate (`402`), Brute Force | `covered` | `SAR-005` |
| `PaymentController` (`/payments/*`) | Authenticated Member ↔ Private Disk & Admin Viewer | IDOR, Stored XSS (`image/svg+xml`), Path Traversal | `covered` | `SAR-004` |
| `AdminController` (`/admin/*`) | Member ↔ Admin Privilege Boundary | Privilege Escalation, Unsanitized File Upload (`.svg`) | `covered` | `SAR-003` |
| `NetflixImapService` (`/imap/fetch`) | Untrusted Email HTML ↔ Dashboard DOM & Outbound Links | Stored/DOM XSS, Open Redirect / `javascript:` URI, TLS MITM | `covered` | Hardening Note |
| `Blade Views & Alpine.js` (`resources/views/*`) | Server Data / JSON API ↔ Browser DOM (`x-text` / `{{ }}`) | DOM XSS, CSRF Protection | `covered` | Clean (All dynamic outputs use `{{ }}` or `x-text`) |

* **Ledger Totals:** `7` units covered, `0` unvalidated candidates, `0` blocked, `1` out-of-scope (`vendor/` third-party libraries).
* **Final Coverage-Critic Result:** Clean pass for `quick` profile.

