# 4. Design Brief Documentation (DBD)
**Project Name:** Netflix Household & Split-Billing Portal ("Netflix Household Hub")  
**Document Version:** 1.0  
**Design System:** Cinema Dark Utility (Tailwind CSS + Alpine.js Bento Grid)

---

## 1. Aesthetic Direction & Design Philosophy

The visual language combines **Netflix Living-Room Cinema** (deep obsidian backgrounds, subtle crimson ambient lighting, high-contrast scannable typography) with **Modern Fintech Utility** (crisp 1px zinc borders, semantic status chips, tabular numerals for currency and countdown timers).

### Core Design Principles
1. **Living-Room Glare Free (Dark-First):** Members frequently open this portal on their phones in a darkened room while looking at a Smart TV prompt. The entire interface uses deep neutral dark surfaces (`#09090b` to `#18181b`) with zero blinding white backgrounds.
2. **Zero-Navigation Bento Workspace:** Every core job-to-be-done (TV Code Fetcher, Password/PIN Vault, Monthly DuitNow Payment, and 5-Member Ledger) is visible at a glance on a single 12-column Bento Grid without page reloads.
3. **Progressive Disclosure for Sensitive Actions:** Destructive or sensitive controls (Step-Up OTP entry, DuitNow QR upload drawer, Admin Control Plane) expand smoothly in place or via a slide-over drawer (`x-transition`) so the dashboard stays uncluttered.

---

## 2. Color System & Semantic Tokens

All color pairings are calibrated against **WCAG 2.2 AA** contrast requirements (`>= 4.5:1` for normal body copy, `>= 3.0:1` for large text and UI boundaries).

### 2.1 Surface & Structural Palette
| Semantic Token | Tailwind Class | Hex / Value | Usage |
| :--- | :--- | :--- | :--- |
| `surface-canvas` | `bg-zinc-950` | `#09090b` | Root page background & inset input wells |
| `surface-card` | `bg-zinc-900/80` | `rgba(24, 24, 27, 0.8)` | Primary Bento cards with `backdrop-blur` |
| `surface-elevated` | `bg-zinc-900` | `#18181b` | Modals, slide-over drawers, dropdown menus, toast notifications |
| `surface-interactive` | `bg-zinc-800` / `hover:bg-zinc-700` | `#27272a` / `#3f3f46` | Secondary buttons, copy triggers, avatar badges |
| `border-subtle` | `border-zinc-800` | `#27272a` | 1px card hairlines, table dividers, input borders |
| `ambient-glow` | `bg-red-600/10 blur-[140px]` | `rgba(220, 38, 38, 0.10)` | Top ambient cinema spotlight (`pointer-events-none`) |

### 2.2 Brand & Semantic Status Tokens
| State / Role | Background & Border | Text Token | Hex (Text) | Meaning |
| :--- | :--- | :--- | :--- | :--- |
| **Brand Primary** | `bg-red-600 hover:bg-red-500` | `text-white` | `#ffffff` on `#dc2626` | Primary CTA ("Fetch Latest Code", "Send Sign-In Link", Brand Badge) |
| **Paid / Approved** | `bg-emerald-950/60 border-emerald-800/80` | `text-emerald-300` / `text-emerald-400` | `#6ee7b7` / `#34d399` | Settled monthly share, verified step-up session, active approval button |
| **Verifying / Warning** | `bg-amber-950/50 border-amber-800/70` | `text-amber-300` / `text-amber-400` | `#fcd34d` / `#fbbf24` | Receipt uploaded awaiting Admin review, OTP prompt, Demo switcher indicator |
| **Overdue / Danger** | `bg-rose-950/40 border-rose-800/70` | `text-rose-300` / `text-rose-400` | `#fda4af` / `#fb7185` | Overdue payment lock, rejected receipt, IMAP/OTP validation error |
| **Pending / Neutral** | `bg-zinc-900 border-zinc-800` | `text-zinc-400` | `#a1a1aa` | Unpaid share prior to due date, inactive historical tabs |

---

## 3. Typography & Numeric Discipline

* **Font Stack:** System UI Sans (`Inter`, `-apple-system`, `BlinkMacSystemFont`, `Segoe UI`, `Roboto`, `sans-serif`) for instant zero-FOUT rendering; `font-mono` (`JetBrains Mono`, `ui-monospace`, `SFMono-Regular`) for credentials, PINs, OTPs, and timers.
* **Type Scale:**
  * **Hero Brand Title (`/login`):** `text-3xl sm:text-4xl font-extrabold tracking-tight` (`30px–36px`)
  * **Page Greeting (`/dashboard`):** `text-xl sm:text-2xl font-bold text-white` (`20px–24px`)
  * **Bento Card Heading:** `text-base sm:text-lg font-bold text-white` (`16px–18px`)
  * **Section Eyebrow Label:** `text-xs font-bold uppercase tracking-wider` (`12px`, `letter-spacing: 0.05em`)
  * **Body & Table Copy:** `text-sm text-zinc-300` (`14px`)
  * **Helper & Metadata Copy:** `text-xs text-zinc-400` (`12px`) and `text-[11px] text-zinc-500` (`11px`)
* **Monospace & Tabular Numerals:**
  * All 4-digit TV verification codes, 4-digit Profile Lock PINs, 6-digit OTP inputs, countdown timers (`MM:SS`), and RM currency figures use `font-mono` or tabular numeric alignment so layout width never jitters as timers tick down.

---

## 4. Spatial Architecture & 12-Column Bento Grid

On desktop viewports (`lg:grid-cols-12`, container `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`), the dashboard arranges its four core modules into an asymmetric **7 / 5** and **5 / 7** visual rhythm (`gap-6` / `24px`):

```text
+-----------------------------------------------------------------------------------+
| STICKY TOP NAVBAR (h-16, backdrop-blur-xl)                                        |
| [N] Household Hub (Netflix Premium 4K)     [Switch] [Admin (1)] [Avatar] [Logout] |
+-----------------------------------------------------------------------------------+
| Welcome back, Firdaus                                      Sunday, 01 Nov 2026    |
+-------------------------------------------------+---------------------------------+
| BENTO CARD 1 (lg:col-span-7)                    | BENTO CARD 2 (lg:col-span-5)    |
| TV Verification Codes                           | Account Credentials             |
| ----------------------------------------------- | ------------------------------- |
| [Fetch Latest Code]                             | Netflix Email:  [Copy]          |
|                                                 | Master Pass:    [Reveal] [Copy] |
| +---------------------------------------------+ |                                 |
| | [8492] Alex • Samsung QLED 4K    [12:41]    | | +--------------+ +------------+ |
| |        [Approve on Netflix ↗]               | | | Profile:     | | 4-Digit PIN| |
| +---------------------------------------------+ | | Firdaus      | | 1 0 2 4    | |
|                                                 | +--------------+ +------------+ |
+---------------------------------+---------------+---------------------------------+
| BENTO CARD 3 (lg:col-span-5)    | BENTO CARD 4 (lg:col-span-7)                    |
| My Monthly Payment              | Household Payment Tracker                       |
| ------------------------------- | ----------------------------------------------- |
| Cycle: 2026-11       [PAID]     | [2026-11 (Now)] [2026-10] [2026-09] ...         |
|                                 |                                                 |
| Monthly Share:   RM 11.00       | Member           Profile    Share     Status    |
| Advance Credit:  RM 0.00        | Firdaus (You)    Firdaus    RM 11.00  [PAID]    |
| Due Date:        07 Nov 2026    | Alex Tan         Alex       RM 11.00  [PAID]    |
|                                 | Sarah Lim        Sarah      RM 11.00  [VERIFY]  |
| [View DuitNow QR / Re-upload]   | Amirul Hakim     Amirul     RM 11.00  [PENDING] |
|                                 | Chloe Wong       Chloe      RM 11.00  [OVERDUE] |
+---------------------------------+-------------------------------------------------+
```

### Responsive Breakpoint Behavior
* **Mobile (`< 640px`):** Single-column vertical stack (`grid-cols-1`). Top priority is **Card 1 (TV Verification Codes)** right at the top of the viewport so a user holding their phone in front of their TV doesn't have to scroll.
* **Tablet (`640px – 1023px`):** Single-column cards with 2-column internal sub-grids for profile/PIN boxes and payment metrics.
* **Desktop (`>= 1024px`):** Full 12-column asymmetric Bento Grid (`7/5` top row, `5/7` bottom row).

---

## 5. Component Specifications & Interactive States

### 5.1 Card 1: TV Verification Request Item
* **Empty State:** Dashed container (`border-dashed border-zinc-800/80`) with TV icon and clear 2-step instructions: *"First, choose 'Send Email' on your TV screen, then tap 'Fetch Latest Code' above."*
* **Active Request State:**
  * Left badge displays the 4-digit code (`bg-red-600/15 border-red-600/30 text-red-400 font-mono text-lg`) or link icon.
  * Center displays **Profile Name** (`text-white font-semibold`), **Device Type** (`bg-zinc-900 text-zinc-300`), and relative receipt time (`2 minutes ago`).
  * Right displays a live ticking countdown pill (`MM:SS` in `text-emerald-400`, turning `text-rose-400` when `< 120s` remain) and an emerald CTA button **"Approve on Netflix ↗"**.

### 5.2 Card 2: Encrypted Credentials & Step-Up OTP Drawer
* **Default Masked State:** Password reads `••••••••••••` in `text-zinc-500`.
* **Overdue Locked State (`isOverdueLocked = true`):** Renders a rose alert banner inside the card with two immediate recovery actions: **"Pay Now"** (scrolls/opens DuitNow modal) and **"Use 24h Emergency Access"** (triggers one-time grace bypass).
* **Step-Up OTP Prompt State (`otpPromptOpen = true`):** Smoothly reveals an amber-bordered panel (`border-amber-600/40 bg-amber-950/20`) with a 6-digit numeric input (`maxlength="6"`, `tracking-widest font-mono`) and an **[Unlock]** button.
* **Revealed State (`revealedPassword != null`):** Replaces dots with the plaintext password in `text-emerald-400`, reveals a `[Copy]` button, and shows a live countdown badge: `Hiding in 15s`.

### 5.3 Card 3 & Modal: DuitNow Payment & Receipt Uploader
* Clicking **"Pay / Upload Receipt"** opens a centered modal backdrop (`bg-black/80 backdrop-blur-sm`) containing:
  * **DuitNow QR Preview:** High-contrast white frame around the QR image (or realistic vector QR placeholder) so banking apps can scan it off the screen effortlessly.
  * **Bank Details Box:** Bank Name, Account Holder, and Account Number with one-click clipboard copy.
  * **Upload Form:** File input (`accept=".jpg,.jpeg,.png,.pdf"`), optional reference note input (`Maybank2u Ref #...`), and submit CTA.

### 5.4 Admin Control Plane Drawer (`role === 'admin'`)
* Triggered from the navbar **Admin** button (which displays an amber notification badge when `pendingReceipts->count() > 0`).
* Slides in from the right (`max-w-2xl w-full bg-zinc-900 border-l border-zinc-800`) with 4 tabbed views:
  1. **Receipt Queue:** Side-by-side payment metadata, **"View Receipt ↗"** preview link, and one-click **[Approve]** (`bg-emerald-600`) / **[Reject]** (`bg-rose-600/20`) buttons.
  2. **Vault & DuitNow Settings:** Form to rotate the master Netflix password, adjust monthly cost/billing day, and upload a new DuitNow QR image.
  3. **Household Members:** Per-member accordion/cards to update whitelisted email, profile name, 4-digit PIN, monthly share, and advance credit balance (`credit_balance`).
  4. **Security Audit Logs:** Chronological table of the latest 15 security events with actor, action key, metadata JSON, IP address, and relative timestamp.

---

## 6. Motion, Feedback & Accessibility Guidelines
* **Staggered Entry Animation:** Cards animate in on load using subtle upward fades (`fadeInUp 0.4s ease-out` with `0.1s`, `0.2s`, `0.3s` delays).
* **Clipboard & Action Toast:** Copying an email, password, PIN, code, or bank account number triggers a fixed bottom-right toast (`fixed bottom-5 right-5`) that auto-dismisses after `3.2` seconds.
* **Keyboard & Screen Reader Support:**
  * All modals and drawers support closing via backdrop click or close button.
  * Interactive icon-only buttons (`Sign Out`) include explicit `title` attributes.
  * Form inputs have explicit `<label>` elements and validation error messages rendered in `text-rose-400`.

