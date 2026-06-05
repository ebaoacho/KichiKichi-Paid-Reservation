# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A WordPress plugin (`early-reservation-system.php`) for paid restaurant reservations. It owns reservation, capacity, and calendar tables, can migrate initial business-day data from the legacy `{prefix}calendar` table, and handles Stripe payments, 5-language emails, and transactional seat holds.

## No Build Step

This is a PHP WordPress plugin — no compilation, bundling, or package manager. Deploy by copying the directory into `wp-content/plugins/`. There are no automated tests.

## Plugin Entry Point & Hook Registration

All constants, global functions, `require_once` chains, and WordPress hook registrations (`add_action`, `add_shortcode`, `register_activation_hook`) live in `early-reservation-system.php`. When adding a new AJAX action or REST route, register it there.

The `require_once` load order in the entry point follows dependency direction and must be respected when adding new files:

```
Infrastructure → Repositories → Services → Validators → Controllers → Supporting classes
```

## Architecture

The codebase is organized into four logical layers inside `includes/`:

| Layer | Directory | Responsibility |
|-------|-----------|----------------|
| **Controllers** | `includes/Controllers/` | AJAX/REST entry points — verify nonce, call validator + service, return `wp_send_json_*` |
| **Validators** | `includes/Validators/` | Sanitize and validate raw `$_POST` input; return clean data or `WP_Error` |
| **Services** | `includes/Services/` | Business logic (hold creation, payment flow, cancellation, email dispatch) |
| **Repositories** | `includes/Repositories/` | All `$wpdb` queries — one class per table |
| **Infrastructure** | `includes/Infrastructure/` | `StripeClient`, `EnvLoader`, `StripeConfig`, `EmailConfig`, `DevMailer` |

Supporting classes that don't fit the layered model:
- `includes/class-kkpay-activator.php` — DB table creation via `dbDelta()`, cron scheduling
- `includes/class-kkpay-admin.php` — Admin menu, reservations list, calendar view
- `includes/class-kkpay-cron.php` — WP-Cron: deletes expired holds every minute
- `includes/kkpay-messages.php` — All 5-language UI strings, defines `KKPAY_MESSAGES` constant

## Data Flow

```
User action → AJAX/REST → Controller → Validator → Service → Repository → DB
                                                  ↓
                                          StripeClient (Stripe API)
                                                  ↓
                                          EmailService → wp_mail
```

## Coding Conventions

Full reference: `doc/11_coding_conventions.md`.

**PHP style:**
- Indent with 4 spaces (no tabs).
- Use `array()` syntax — never `[]` (WordPress coding standards).
- Every PHP file must begin with `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- No closing `?>` tag.

**Naming:**
- Files: `class-kkpay-{name}.php` (kebab-case).
- Classes: `KKPAY_{Name}_Controller` / `_Service` / `_Repository` / `_Validator`.
- Methods and variables: `snake_case`.
- AJAX action names: `kkpay_{verb}_{noun}`.
- Constants: `KKPAY_{UPPER_SNAKE}`.

**Return value contract (Services and Repositories):**
- Success → `stdClass`, `stdClass[]`, `int`, `string`, or `array` depending on what makes sense.
- Failure → `WP_Error`. Never use `false` or `0` to signal an error.
- `find_*` methods may return `null` when no record is found.

**Controller error-check pattern:**
```php
$result = KKPAY_Foo_Service::method( ... );
if ( is_wp_error( $result ) ) {
    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}
wp_send_json_success( array( ... ) );
```

**User-facing error messages:** always use `kkpay_msg( $key, $lang )`. Never hard-code strings. New message keys require translations in all 5 languages in `includes/kkpay-messages.php`. Admin-only `wp-admin` messages are Japanese fixed for the shop staff and should be centralized in the relevant admin helper instead of repeated inline.

**Git commit messages:** `{type}: {日本語一行説明}` — e.g. `feat: クーポンコード機能を追加`, `fix: キャンセル時の履歴記録が正しく動作しない不具合を修正`. Types: `feat`, `fix`, `refactor`, `docs`, `chore`.

## Environment Configuration

Secrets are loaded from a `.env` file at the plugin root (`KKPAY_PLUGIN_DIR . '.env'`) via `KKPAY_Env_Loader`. Variables already set in the environment take precedence. Never commit real credentials; the `.env` file is gitignored.

Required `.env` variables:

```
KKPAY_STRIPE_PUBLISHABLE_KEY=pk_...
KKPAY_STRIPE_SECRET_KEY=sk_...
KKPAY_STRIPE_WEBHOOK_SECRET=whsec_...
KKPAY_FROM_EMAIL=...
KKPAY_FROM_NAME=...
```

Optional dev variable: `KKPAY_DEV_MAIL=true` — when set, `KKPAY_Dev_Mailer` redirects all outbound mail to Mercury SMTP at `127.0.0.1:25` (XAMPP local mail). Can also be set as a PHP constant in `wp-config.php`.

## Shortcodes

| Shortcode | Template | Notes |
| --- | --- | --- |
| `[kkpay_reservation_form]` | `templates/reservation-form.php` | Normal reservation form |
| `[kkpay_payment_page]` | `templates/payment-page.php` | Payment step; loads Stripe.js |
| `[kkpay_my_reservation]` | `templates/my-reservation.php` | Reservation lookup and cancel |
| `[kkpay_premium_payment]` | `templates/premium-payment.php` | Special premium payment via token URL |
| `[kkpay_premium_cancel]` | `templates/premium-cancel.php` | Special premium cancellation via token URL |
| `[kkpay_customer_calendar]` | `templates/customer-calendar.php` | Customer-facing business day calendar |
| `[kkpay_legal_policies]` | `templates/legal-policies.php` | Customer-facing cancellation, privacy, and terms page with 5 language tabs |

## Critical: Race Condition Prevention on Hold Creation

`HoldService::create()` uses `START TRANSACTION` + `SELECT ... FOR UPDATE` on both `kkpay_holds` and `kkpay_reservations` before inserting. This is intentional and must be preserved when refactoring.

## Database Tables

Custom tables include `kkpay_holds`, `kkpay_reservations`, `kkpay_cancellations`, `kkpay_accepted_dates`, `kkpay_premium_reservations`, `kkpay_slot_capacities`, `kkpay_reservation_events`, and `kkpay_calendar_days`. The legacy external `{prefix}calendar` table is an optional one-time migration source only; calendar reads and writes use `kkpay_calendar_days`. Schema is in `class-kkpay-activator.php`.

Key constraints:

- `kkpay_reservations` has `UNIQUE KEY (stripe_payment_intent_id)` and `UNIQUE KEY (email, reservation_date, time_slot)` — duplicate inserts are resolved by idempotency checks on `stripe_payment_intent_id`.
- `kkpay_holds.hold_token` is UNIQUE (64-char hex from `random_bytes(32)`).
- `kkpay_accepted_dates` has `UNIQUE KEY (reservation_date, time_slot)`.

`dbDelta()` is used for all table creation. Write `CREATE TABLE` (not `CREATE TABLE IF NOT EXISTS`) — `dbDelta()` handles existence checks internally.

The `kkpay_accepted_dates` table drives the **normal/premium mode switch** — see "Booking Modes" below.

## Booking Modes

The plugin operates in one of two modes based on whether `kkpay_accepted_dates` has any rows:

- **Normal mode** (empty table): accepts reservations for any calendar-open day within `ACCEPT_DAYS_BEFORE` days, starting `ACCEPT_HOUR_JST`:00 JST on the cutoff day. Per-slot capacity defaults to `KKPAY_MAX_CAPACITY`.
- **Premium mode** (table has rows): only `enabled=1` dates/slots are bookable. Booking opens at midnight JST on `target_date − ACCEPT_DAYS_BEFORE`. Per-slot capacity is read from `kkpay_accepted_dates.capacity`. `KKPAY_ACCEPT_HOUR_JST` is ignored in this mode.

Admin uses `kkpay_save_slot_capacity` AJAX action (seat-capacity tab) to upsert rows and toggle the mode.

Capacity reads from `kkpay_accepted_dates` are not row-locked during customer hold creation. This is acceptable for the current admin workflow, but be careful if adding live capacity edits during high-traffic booking windows.

## Stripe Integration

- **PaymentIntent flow**: frontend calls `kkpay_create_payment_intent` → gets `client_secret` → Stripe.js confirms card → frontend calls `kkpay_confirm_reservation`.
- **Webhook** (`POST /wp-json/kkpay/v1/webhook`): handles `payment_intent.succeeded` and external `charge.refunded` events. Signature verified with HMAC-SHA256, ±300s tolerance.
- The webhook's `payment_intent.succeeded` handler is a **fallback** for when the user closes the browser before the confirm AJAX completes. Both paths share `KKPAY_Reservation_Service::create_from_hold()` and are idempotent.
- Webhook handling branches on Stripe metadata `type=premium_reservation`; normal reservation PaymentIntents continue using the existing flow.
- Secrets are read from the `.env` / environment variables. Never store Stripe secrets in `wp_options` or expose the secret key to the frontend.

## Multi-Language

Five languages: `en`, `ja`, `ko`, `zh-CN`, `zh-TW`. All user-facing strings go through `kkpay_msg($key, $lang)` defined in `includes/kkpay-messages.php`. The same `KKPAY_MESSAGES` constant is passed to JavaScript via `wp_localize_script`.

Normal and special premium reservations validate names as ASCII/English-style names (`A-Z`, spaces, dot, apostrophe, hyphen).

## WordPress Options (Admin Settings)

| Option key | Purpose |
| --- | --- |
| `KKPAY_STRIPE_PUBLISHABLE_KEY` | Stripe publishable key |
| `KKPAY_STRIPE_SECRET_KEY` | Stripe secret key |
| `KKPAY_STRIPE_WEBHOOK_SECRET` | Webhook signing secret (`whsec_…`) |
| `KKPAY_FROM_EMAIL` | Outbound email "From" address |
| `KKPAY_FROM_NAME` | Outbound email "From" name |

## Key Constants (defined in entry point)

| Constant | Value | Meaning |
| --- | --- | --- |
| `KKPAY_AMOUNT` | 13 | Unit charge per seat (USD) — normal reservations |
| `KKPAY_CURRENCY` | `'usd'` | Stripe currency code |
| `KKPAY_STRIPE_AMOUNT_MULTIPLIER` | 100 | Multiplier to convert to Stripe cents |
| `KKPAY_MAX_CAPACITY` | 8 | Bar counter capacity limit and default max people per Bar slot |
| `KKPAY_TABLE_MAX_CAPACITY` | 6 | Table capacity limit |
| `KKPAY_MAX_PEOPLE` | 8 | Legacy constant; normal booking seat choices are now limited by actual remaining capacity |
| `KKPAY_HOLD_MINUTES` | 5 | Hold expiration window |
| `KKPAY_ACCEPT_DAYS_BEFORE` | 3 | Booking window (days ahead) |
| `KKPAY_ACCEPT_HOUR_JST` | 13 | **Normal mode only.** Hour (JST) at which bookings open on the cutoff day. Ignored in premium mode. |
| `KKPAY_PREMIUM_AMOUNT` | 32 | Unit charge per seat (USD) — special premium reservations |
| `KKPAY_PREMIUM_CURRENCY` | `'usd'` | Stripe currency code for premium |
| `KKPAY_PREMIUM_MAX_PEOPLE` | 8 | Max seats for special premium reservations |
| `KKPAY_SLOT_TYPES` | array | Maps slot keys (`slot_1`–`slot_6`) to `'lunch'` or `'dinner'` |
| `KKPAY_SLOT_LABELS` | array | Per-language display labels for each slot key |

## AJAX Actions

All public AJAX calls use nonce `kkpay_nonce` (`check_ajax_referer( 'kkpay_nonce', 'nonce' )`). CSV export uses `kkpay_export`.

Public (logged-in and anonymous):

| Action | Handler |
| --- | --- |
| `kkpay_get_available_slots` | `KKPAY_Reservation_Controller::ajax_get_available_slots` |
| `kkpay_create_hold` | `KKPAY_Hold_Controller::ajax_create_hold` |
| `kkpay_create_payment_intent` | `KKPAY_Payment_Controller::ajax_create_payment_intent` |
| `kkpay_confirm_reservation` | `KKPAY_Payment_Controller::ajax_confirm_reservation` |
| `kkpay_check_reservation` | `KKPAY_Reservation_Controller::ajax_check_reservation` |
| `kkpay_cancel_reservation` | `KKPAY_Cancellation_Controller::ajax_cancel_reservation` |
| `kkpay_premium_create_payment_intent` | `KKPAY_Premium_Reservation_Controller::ajax_create_payment_intent` |
| `kkpay_premium_confirm_payment` | `KKPAY_Premium_Reservation_Controller::ajax_confirm_payment` |
| `kkpay_premium_cancel_reservation` | `KKPAY_Premium_Reservation_Controller::ajax_cancel_reservation` |

Admin only (`manage_options`):

| Action | Handler |
| --- | --- |
| `kkpay_load_admin_list` | `KKPAY_Admin_Controller::ajax_load_admin_list` |
| `kkpay_export_csv` | `KKPAY_Admin_Controller::ajax_export_csv` |
| `kkpay_save_slot_capacity` | `KKPAY_Admin_Controller::ajax_save_slot_capacity` |
| `kkpay_calendar_save_day` | `KKPAY_Admin_Controller::ajax_save_calendar_day` |
| `kkpay_premium_issue_payment_link` | `KKPAY_Premium_Reservation_Controller::ajax_issue_payment_link` |
| `kkpay_premium_schedule_reservation` | `KKPAY_Premium_Reservation_Controller::ajax_schedule_reservation` |
| `kkpay_premium_issue_cancel_link` | `KKPAY_Premium_Reservation_Controller::ajax_issue_cancel_link` |
| `kkpay_premium_export_csv` | `KKPAY_Premium_Reservation_Controller::ajax_export_csv` |

## Operational Notes

- `hold_token` is passed in the payment page URL query string. Tokens are short-lived and slot-specific, but they may appear in browser history, access logs, or Referer headers.
- `kkpay_find_shortcode_page_url()` scans published pages for shortcode placement. This is acceptable for the current small site; add caching if the page count grows significantly.

## Cancellation Policy

キャンセルしても返金は行わない。プラグインのキャンセル処理から Stripe `/v1/refunds` は呼ばない。

- `KKPAY_Cancellation_Service::cancel()` は常に `refund_status = 'none'`, `refund_amount = 0`, `stripe_refund_id = null` を記録する。
- `payment_status` はキャンセル前の値を維持し、キャンセル日時は `cancelled_at` に記録する。
- フロントエンドの「キャンセルボタン」は `can_cancel` フラグで制御される。
- `charge.refunded` Webhook は、Stripe ダッシュボード等で外部返金された場合の同期用として扱う。
- Logic lives in `CancellationService` (`includes/Services/class-kkpay-cancellation-service.php`). Cancellations are audited in `kkpay_cancellations` table.

### Special Premium Reservation Exception

スペシャルプレミアム予約では、通常予約の返金なしルールをそのまま適用しない。予約日の3日前までは Stripe Refund API で支払い済み全額を返金し、3日前を過ぎた場合は返金しない。

## Special Premium Reservations

Design source: `doc/12_special_premium_reservation_design.md`. Implementation is complete.

Core behavior:
- Master issues a customer-specific tokenized payment link from the admin "スペシャルプレミアム予約" tab.
- Payment link expires 24 hours after issue.
- Customer page: `[kkpay_premium_payment]`. Customer enters language, name, email, seats. Price: USD 32/seat, up to 8 seats.
- After payment, master schedules the reservation date and slot in admin. Date range: today through one month later (JST).
- On scheduling, a `kkpay_reservations` row is created and linked from `kkpay_premium_reservations.reservation_id`, so existing capacity calculations include it.
- Master can change the date/slot after scheduling (capacity check excludes current seats; sends schedule-change email).
- Cancellation link is issued only after scheduling; it has no expiry but is single-use.
- Cancellation page: `[kkpay_premium_cancel]`.

## Planned: Same-Day Reservation Integration

Design source: `doc/14_same_day_reservation_integration_design.md`.

The goal is to unify same-day reservations (currently in the separate `kichikichi-reservation-system` plugin) with the paid reservation base, preventing double-booking across all reservation types. Existing UI/UX is preserved; only the internal storage and capacity logic changes.

Key design decisions:
- All reservation types (`same_day`, `premium`, `special_premium`) converge on `kkpay_reservations` with a `reservation_type` column.
- A new `kkpay_slot_capacities` table (keyed on `capacity_date + time_slot + seating_preference`) replaces `kkpay_accepted_dates` as the source of truth for seat counts and replaces it as the `SELECT ... FOR UPDATE` lock target.
- `seating_preference` values: `Table` (same-day only) or `Bar` (premium and special premium, fixed).
- Same-day cancellation sets `status = cancelled` instead of deleting the row.
- A new `kkpay_reservation_events` audit log table tracks all state changes.

Implementation is split across PRs (PR 0–9); see the design doc for the full breakdown. No code for this feature exists yet.

## Timezone

Always `Asia/Tokyo` (JST). All `DateTimeImmutable` instances must be constructed with `new DateTimeZone('Asia/Tokyo')`.
