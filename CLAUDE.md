# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A WordPress plugin (`early-reservation-system.php`) for paid restaurant reservations. It integrates with an existing "KichiKichi Calendar" plugin (which provides the `{prefix}calendar` table) and handles Stripe payments, 5-language emails, and transactional seat holds.

## No Build Step

This is a PHP WordPress plugin — no compilation, bundling, or package manager. Deploy by copying the directory into `wp-content/plugins/`. There are no automated tests.

## Plugin Entry Point & Hook Registration

All constants, global functions, `require_once` chains, and WordPress hook registrations (`add_action`, `add_shortcode`, `register_activation_hook`) live in `early-reservation-system.php`. When adding a new AJAX action or REST route, register it there.

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

| Shortcode | Template | Assets loaded |
| --- | --- | --- |
| `[kkpay_reservation_form]` | `templates/reservation-form.php` | `kkpay-form.js`, `kkpay-form.css` |
| `[kkpay_payment_page]` | `templates/payment-page.php` | above + `stripe-js` (v3) |
| `[kkpay_my_reservation]` | `templates/my-reservation.php` | `kkpay-mypage.js`, `kkpay-mypage.css` |

## Critical: Race Condition Prevention on Hold Creation

`HoldService::create()` uses `START TRANSACTION` + `SELECT ... FOR UPDATE` on both `kkpay_holds` and `kkpay_reservations` before inserting. This is intentional and must be preserved when refactoring.

## Database Tables

Four custom tables (prefix + `kkpay_holds`, `kkpay_reservations`, `kkpay_cancellations`, `kkpay_accepted_dates`) plus read-only access to the external `{prefix}calendar` table. Schema is in `class-kkpay-activator.php`.

Key constraints:

- `kkpay_reservations` has `UNIQUE KEY (stripe_payment_intent_id)` and `UNIQUE KEY (email, reservation_date, time_slot)` — duplicate inserts are resolved by idempotency checks on `stripe_payment_intent_id`.
- `kkpay_holds.hold_token` is UNIQUE (64-char hex from `random_bytes(32)`).
- `kkpay_accepted_dates` has `UNIQUE KEY (reservation_date, time_slot)`.

The `kkpay_accepted_dates` table drives the **normal/premium mode switch** — see "Booking Modes" below. Planned special premium reservations add a fifth custom table, `kkpay_premium_reservations`. See `doc/12_special_premium_reservation_design.md` before implementation.

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
- Secrets are read from the `.env` / environment variables. Never store Stripe secrets in `wp_options` or expose the secret key to the frontend.

## Multi-Language

Five languages: `en`, `ja`, `ko`, `zh-CN`, `zh-TW`. All user-facing strings go through `kkpay_msg($key, $lang)` defined in `early-reservation-system.php`. The same `KKPAY_MESSAGES` constant is passed to JavaScript via `wp_localize_script`.

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
| `KKPAY_AMOUNT` | 13 | Unit charge per seat (USD) |
| `KKPAY_CURRENCY` | `'usd'` | Stripe currency code |
| `KKPAY_STRIPE_AMOUNT_MULTIPLIER` | 100 | Multiplier to convert to Stripe cents |
| `KKPAY_MAX_CAPACITY` | 8 | Default max people per slot (confirmed + held) when no `accepted_dates` row |
| `KKPAY_MAX_PEOPLE` | 4 | Legacy constant; normal booking seat choices are now limited by actual remaining capacity |
| `KKPAY_HOLD_MINUTES` | 5 | Hold expiration window |
| `KKPAY_ACCEPT_DAYS_BEFORE` | 3 | Booking window (days ahead) |
| `KKPAY_ACCEPT_HOUR_JST` | 13 | **Normal mode only.** Hour (JST) at which bookings open on the cutoff day. Ignored in premium mode. |
| `KKPAY_SLOT_TYPES` | array | Maps slot keys (`slot_1`–`slot_6`) to `'lunch'` or `'dinner'` |
| `KKPAY_SLOT_LABELS` | array | Per-language display labels for each slot key |

## AJAX Actions

Public (logged-in and anonymous):

| Action | Handler |
| --- | --- |
| `kkpay_get_available_slots` | `KKPAY_Reservation_Controller::ajax_get_available_slots` |
| `kkpay_create_hold` | `KKPAY_Hold_Controller::ajax_create_hold` |
| `kkpay_create_payment_intent` | `KKPAY_Payment_Controller::ajax_create_payment_intent` |
| `kkpay_confirm_reservation` | `KKPAY_Payment_Controller::ajax_confirm_reservation` |
| `kkpay_check_reservation` | `KKPAY_Reservation_Controller::ajax_check_reservation` |
| `kkpay_cancel_reservation` | `KKPAY_Cancellation_Controller::ajax_cancel_reservation` |

Admin only (`manage_options`):

| Action | Handler |
| --- | --- |
| `kkpay_load_admin_list` | `KKPAY_Admin_Controller::ajax_load_admin_list` |
| `kkpay_export_csv` | `KKPAY_Admin_Controller::ajax_export_csv` |
| `kkpay_save_slot_capacity` | `KKPAY_Admin_Controller::ajax_save_slot_capacity` |

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

## Planned Special Premium Reservations

Design source: `doc/12_special_premium_reservation_design.md`.

Core behavior:

- Master issues a customer-specific tokenized payment link from a new admin tab.
- Payment link expires 24 hours after issue.
- Customer page is a separate shortcode: `[kkpay_premium_payment]`.
- Customer enters language, name, email, and seats. Premium price is USD 32 per seat, up to 8 seats.
- After payment, master schedules the reservation date and slot in admin.
- Premium reservation date scheduling/changing can be selected from today through one month later, using JST.
- Seat-capacity admin settings are shown from today through the end of the month two months later.
- Time slot must be one of existing `slot_1` through `slot_6`.
- On scheduling, create a normal `kkpay_reservations` row and link it from `kkpay_premium_reservations.reservation_id`, so existing capacity calculations include the premium reservation.
- Reflect premium reservations as the customer-selected `number_of_people`.
- Master can change the reservation date/slot after scheduling. Changes keep the paid seat count, run capacity checks against the new slot while excluding the reservation's current seats, and send an automatic schedule-change email.
- Master can issue a cancellation link only after scheduling.
- Cancellation page is a separate shortcode: `[kkpay_premium_cancel]`.
- Cancellation link has no expiry but is single-use.

Implementation shape:

- Add `KKPAY_Premium_Reservation_Repository`, `KKPAY_Premium_Reservation_Service`, `KKPAY_Premium_Reservation_Controller`, and `KKPAY_Premium_Reservation_Validator`.
- Add templates `templates/premium-payment.php`, `templates/premium-cancel.php`, and `templates/admin/premium-reservations-tab.php`.
- Add frontend/admin JS as needed, likely `assets/js/kkpay-premium.js` and `assets/js/kkpay-admin-premium.js`.
- Register all new `require_once`, shortcodes, AJAX actions, admin AJAX actions, and webhook branching in `early-reservation-system.php`.
- Webhook handling must branch on Stripe metadata `type=premium_reservation`; normal reservation PaymentIntents must continue using the existing flow.
- Premium payment completion, scheduling, cancellation, and refund decisions should live in the premium service, not in controllers.

## Timezone

Always `Asia/Tokyo` (JST). All `DateTimeImmutable` instances must be constructed with `new DateTimeZone('Asia/Tokyo')`.
