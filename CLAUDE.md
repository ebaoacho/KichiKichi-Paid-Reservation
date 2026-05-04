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
| **Infrastructure** | `includes/Infrastructure/` | `StripeClient` — wraps `wp_remote_request()` calls to `api.stripe.com` |

Supporting classes that don't fit the layered model:
- `includes/class-kkpay-activator.php` — DB table creation via `dbDelta()`, cron scheduling
- `includes/class-kkpay-admin.php` — Admin menu, reservations list, calendar view
- `includes/class-kkpay-cron.php` — WP-Cron: deletes expired holds every minute
- `includes/class-kkpay-mailer.php` — 5-language email templates, sends via `wp_mail()`

## Data Flow

```
User action → AJAX/REST → Controller → Validator → Service → Repository → DB
                                                  ↓
                                          StripeClient (Stripe API)
                                                  ↓
                                          Mailer (wp_mail)
```

## Critical: Race Condition Prevention on Hold Creation

`HoldService::create()` (originally in `class-kkpay-hold.php`) uses `START TRANSACTION` + `SELECT ... FOR UPDATE` on both `kkpay_holds` and `kkpay_reservations` before inserting. This is intentional and must be preserved when refactoring.

## Database Tables

Three custom tables (prefix + `kkpay_holds`, `kkpay_reservations`, `kkpay_cancellations`) plus read-only access to the external `{prefix}calendar` table. Schema is in `class-kkpay-activator.php`.

Key constraints:
- `kkpay_reservations` has a `UNIQUE KEY (email, reservation_date, time_slot)` — duplicate inserts are silently resolved by idempotency checks using `stripe_payment_intent_id`.
- `kkpay_holds.hold_token` is UNIQUE (64-char hex from `random_bytes(32)`).

## Stripe Integration

- **PaymentIntent flow**: frontend calls `kkpay_create_payment_intent` → gets `client_secret` → Stripe.js confirms card → frontend calls `kkpay_confirm_reservation`.
- **Webhook** (`POST /wp-json/kkpay/v1/webhook`): handles `payment_intent.succeeded` and `charge.refunded`. Signature verified with HMAC-SHA256, ±300s tolerance.
- Deployment settings are read from environment variables through config classes. Never store Stripe secrets or mail sender settings in `wp_options`, and never expose the Stripe secret key to the frontend.

## Multi-Language

Five languages: `en`, `ja`, `ko`, `zh-CN`, `zh-TW`. All user-facing strings go through `kkpay_msg($key, $lang)` defined in the entry point. The same `KKPAY_MESSAGES` constant is passed to JavaScript via `wp_localize_script`.

## WordPress Options (Admin Settings)

| Option key | Purpose |
|------------|---------|
| `KKPAY_STRIPE_PUBLISHABLE_KEY` | Stripe publishable key |
| `KKPAY_STRIPE_SECRET_KEY` | Stripe secret key |
| `KKPAY_STRIPE_WEBHOOK_SECRET` | Webhook signing secret (`whsec_…`) |
| `KKPAY_FROM_EMAIL` | Outbound email "From" address |
| `KKPAY_FROM_NAME` | Outbound email "From" name |

## Key Constants (defined in entry point)

| Constant | Default | Meaning |
|----------|---------|---------|
| `KKPAY_AMOUNT` | 3000 | Unit charge per seat in JPY |
| `KKPAY_MAX_CAPACITY` | 8 | Max people per slot (confirmed + held) |
| `KKPAY_MAX_PEOPLE` | 4 | Max people per single booking |
| `KKPAY_HOLD_MINUTES` | 5 | Hold expiration window |
| `KKPAY_ACCEPT_DAYS_BEFORE` | 3 | Booking window (days ahead) |
| `KKPAY_ACCEPT_HOUR_JST` | 13 | Hour (JST) when booking opens for a date |

## Cancellation Policy

返金は一切なし。キャンセル時は常に `refund_status = 'none'`, `refund_amount = 0` で記録される。Logic lives in `CancellationService` (`includes/Services/class-kkpay-cancellation-service.php`). Cancellations are audited in `kkpay_cancellations` table.

## Timezone

Always `Asia/Tokyo` (JST). All `DateTimeImmutable` instances must be constructed with `new DateTimeZone('Asia/Tokyo')`.
