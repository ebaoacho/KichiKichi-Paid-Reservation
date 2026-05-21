# キチキチ 決済予約システム（kkpay）

既存の「キチキチ予約システム」が管理する営業カレンダーを参照しながら、
Stripe 決済を組み込んだ予約受付機能を提供する WordPress プラグインです。

---

## 目次

1. [動作要件](#動作要件)
2. [導入手順](#導入手順)
3. [Stripe 設定](#stripe-設定)
4. [WordPress ページ設置](#wordpress-ページ設置)
5. [ショートコード一覧](#ショートコード一覧)
6. [定数・設定値一覧](#定数設定値一覧)
7. [管理画面の使い方](#管理画面の使い方)
8. [予約フロー概要](#予約フロー概要)
9. [キャンセルポリシー](#キャンセルポリシー)
10. [メール送信設定（Xserver SMTP）](#メール送信設定xserver-smtp)
11. [トラブルシューティング](#トラブルシューティング)

---

## 動作要件

| 項目 | 条件 |
|---|---|
| PHP | 7.2 以上 |
| WordPress | 5.6 以上 |
| 依存プラグイン | 「キチキチ予約システム」（`{prefix}calendar` テーブルが必要） |
| 推奨プラグイン | WP Mail SMTP（Xserver SMTP 経由送信に使用） |
| Stripe | アカウントおよび API キーが必要 |

---

## 導入手順

### 1. プラグインを有効化する

WordPress 管理画面 → **プラグイン** → 「キチキチ 決済予約システム」を **有効化**

有効化と同時に以下のデータベーステーブルが自動作成されます。

| テーブル名 | 用途 |
|---|---|
| `{prefix}kkpay_holds` | 仮予約（5 分で自動開放） |
| `{prefix}kkpay_reservations` | 本予約（決済完了済み） |
| `{prefix}kkpay_cancellations` | キャンセル履歴 |

> **注意**: 既存プラグインが管理する `{prefix}calendar` テーブルへの書き込みは一切行いません。

### 2. Configure environment settings

Plugin settings are not entered in WordPress admin. Copy `.env.template` to `.env` in this plugin directory before enabling payments.

| Environment variable | Value |
|---|---|
| `KKPAY_STRIPE_PUBLISHABLE_KEY` | `pk_live_...` or `pk_test_...` |
| `KKPAY_STRIPE_SECRET_KEY` | `sk_live_...` or `sk_test_...` |
| `KKPAY_STRIPE_WEBHOOK_SECRET` | `whsec_...` from the Stripe Dashboard |
| `KKPAY_FROM_EMAIL` | Outbound email From address |
| `KKPAY_FROM_NAME` | Outbound email From name |

The plugin loads this `.env` file at startup. `wp-config.php` constants and server-level environment variables with the same names are also supported.

### 3. Stripe Webhook を登録する

Stripe ダッシュボード → **Developers** → **Webhooks** → **Add endpoint**

| 項目 | 値 |
|---|---|
| Endpoint URL | `https://yoursite.com/wp-json/kkpay/v1/webhook` |
| Listen to events | `payment_intent.succeeded`, `charge.refunded`（外部返金同期用） |

> Put the Webhook signing secret (`whsec_...`) in `KKPAY_STRIPE_WEBHOOK_SECRET`.

### 4. WordPress ページを作成する

以下の 3 ページを WordPress で作成し、各ショートコードを本文に貼り付けます。

| ページ | ショートコード | スラッグ例 |
|---|---|---|
| 予約フォームページ | `[kkpay_reservation_form]` | `/reservation/` |
| 決済ページ | `[kkpay_payment_page]` | `/payment/` |
| 予約照会・キャンセルページ | `[kkpay_my_reservation]` | `/my-reservation/` |

### 5. 決済ページ URL を設定する

予約フォームから決済ページへの自動遷移に使用する URL を、
[early-reservation-system.php](early-reservation-system.php) の `wp_localize_script` に追記します。

```php
// early-reservation-system.php の kkpay_enqueue_assets() 内
wp_localize_script( 'kkpay-form', 'kkpay', array(
    // ... 既存の項目 ...
    'payment_page_url' => home_url( '/payment/' ),  // ← 決済ページのURLを追記
) );
```

> スラッグが異なる場合は `'/payment/'` を実際のパスに変更してください。

---

## Stripe 設定

### テストモードでの確認

1. Stripe ダッシュボードで **テストモード** をオンにする
2. 公開キーに `pk_test_...`、シークレットキーに `sk_test_...` を入力
3. テスト用カード番号 `4242 4242 4242 4242`（有効期限・CVC は任意）で決済をテスト
4. 本番稼働前に `pk_live_...` / `sk_live_...` に切り替える

### 3D セキュア対応

Stripe の `confirmCardPayment` を使用しているため、銀行が要求する場合は自動的に 3D セキュア認証画面が表示されます。追加設定は不要です。

---

## WordPress ページ設置

### 予約フォーム `[kkpay_reservation_form]`

日付選択 → スロット選択 → 人数・氏名・メール入力 → 仮予約送信 → 決済ページへ遷移

```
[kkpay_reservation_form]
```

### 決済ページ `[kkpay_payment_page]`

hold_token を URL パラメータ（`?hold_token=xxx&lang=ja`）で受け取り、
Stripe カード決済を行うページ。**予約フォームから自動遷移するため、直接アクセスするページではありません。**

```
[kkpay_payment_page]
```

### 予約照会・キャンセル `[kkpay_my_reservation]`

メールアドレスを入力して予約情報を照会し、キャンセルを実行するページ。

```
[kkpay_my_reservation]
```

---

## ショートコード一覧

| ショートコード | 機能 | 主な Ajax アクション |
|---|---|---|
| `[kkpay_reservation_form]` | 予約フォーム | `kkpay_get_available_slots`, `kkpay_create_hold` |
| `[kkpay_payment_page]` | Stripe 決済 | `kkpay_create_payment_intent`, `kkpay_confirm_reservation` |
| `[kkpay_my_reservation]` | 予約照会・キャンセル | `kkpay_check_reservation`, `kkpay_cancel_reservation` |

---

## 定数・設定値一覧

[early-reservation-system.php](early-reservation-system.php) の先頭部分で以下の定数が定義されています。
**変更が必要な場合はこのファイルを編集してください。**

| 定数名 | デフォルト値 | 説明 |
|---|---|---|
| `KKPAY_AMOUNT` | `3000` | 1席あたりの決済金額（円） |
| `KKPAY_MAX_CAPACITY` | `8` | 各スロットの最大収容人数 |
| `KKPAY_MAX_PEOPLE` | `4` | 1 予約あたりの最大人数 |
| `KKPAY_HOLD_MINUTES` | `5` | 仮予約の有効時間（分） |
| `KKPAY_ACCEPT_DAYS_BEFORE` | `3` | 何日前から予約受付を開始するか |
| `KKPAY_ACCEPT_HOUR_JST` | `13` | 受付開始時刻（JST・時） |

### 予約受付ルール（デフォルト）

- **予約可能範囲**: 本日〜3 日後
- **受付開始タイミング**: 予約希望日の **3 日前 13:00 JST** 以降
  - 例）5 月 10 日（土）の予約 → 5 月 7 日（水）13:00 から受付開始

### Settings and environment values

Deployment settings are loaded from `.env` into environment variables, not saved in `wp_options`.

| Name | Content |
|---|---|
| `KKPAY_STRIPE_PUBLISHABLE_KEY` | Stripe publishable key |
| `KKPAY_STRIPE_SECRET_KEY` | Stripe secret key |
| `KKPAY_STRIPE_WEBHOOK_SECRET` | Webhook signing secret |
| `KKPAY_FROM_EMAIL` | Outbound email From address |
| `KKPAY_FROM_NAME` | Outbound email From name |

---

## 管理画面の使い方

管理画面 → **キチキチ 決済予約** に以下のタブがあります。


### 予約者リストタブ

全予約を **予約日・スロット順** で一覧表示します。

- 日付・スロットでフィルタリング可能
- **CSV エクスポートボタン** で Excel 対応の CSV（UTF-8 BOM）をダウンロード
- 決済状態（`paid` / `pending` / `refunded`）とキャンセル有無を表示
- 通常のキャンセルでは返金せず、`payment_status` はキャンセル前の値を維持

### 営業カレンダータブ

既存プラグインが管理する `{prefix}calendar` テーブルを **読み取り専用** で参照します。
本日から 60 日分の営業日（ランチ・ディナー）を確認できます。

---

## 予約フロー概要

```
① フォーム送信（日付・スロット・人数・氏名・メール・言語）
        ↓
② [サーバー] kkpay_create_hold
   トランザクション + SELECT FOR UPDATE で枠をロック
   確定済み人数 + ホールド中人数 + 申込人数 ≦ 8 を確認
   空きあり → kkpay_holds に INSERT → hold_token を返却
   満席    → ROLLBACK → エラー返却
        ↓
③ [フロント] 決済ページへ遷移
   URL: /payment/?hold_token=xxx&lang=ja
   5 分のカウントダウンタイマー表示
        ↓
④ [サーバー] kkpay_create_payment_intent
   hold_token を検証 → Stripe Payment Intent 作成 → client_secret を返却
        ↓
⑤ [フロント] Stripe.js でカード決済
   stripe.confirmCardPayment(clientSecret, { card: cardElement })
        ↓
⑥ 決済成功 → [フロント] kkpay_confirm_reservation を呼び出し
       ↕（並行して）
   Stripe → [サーバー] Webhook（payment_intent.succeeded）受信
        ↓
⑦ [サーバー] kkpay_reservations に INSERT（paid）
   kkpay_holds のレコードを削除
        ↓
⑧ [サーバー] 予約確認メールを顧客に送信（wp_mail）
        ↓
⑨ [フロント] 決済完了画面を表示
```

> **二重処理防止**: `stripe_payment_intent_id` で既存レコードを照合し、
> クライアント側確定とウェブフック両方から安全に処理できます（冪等性）。

---

## キャンセルポリシー

キャンセルは `[kkpay_my_reservation]` ページのメールアドレス照会から行います。

キャンセルしても返金は行いません。キャンセル処理では Stripe の返金 API を呼び出さず、`kkpay_cancellations` には `refund_status = 'none'`, `refund_amount = 0`, `stripe_refund_id = NULL` を記録します。

---

## メール送信設定（Xserver SMTP）

デフォルトの `wp_mail()` は迷惑メール判定されやすいため、
**WP Mail SMTP** プラグインを使って Xserver の SMTP サーバー経由で送信することを推奨します。

### WP Mail SMTP の設定例

| 項目 | 値 |
|---|---|
| Mailer | Other SMTP |
| SMTP Host | `sv****.xserver.jp`（契約サーバーのホスト名） |
| Encryption | TLS（ポート 587）または SSL（ポート 465） |
| SMTP Username | Xserver で作成したメールアドレス（例: `info@yourdomain.com`） |
| SMTP Password | Xserver メールアカウントのパスワード |
| From Email | Xserver で作成したメールアドレス |
| From Name | 店舗名（例: キチキチ） |

> Xserver のメールアドレスは、Xserver コントロールパネル → **メールアカウント設定** で事前に作成してください。

---

## トラブルシューティング

### 予約フォームに日付が表示されない

- `{prefix}calendar` テーブルに今後の営業日データが登録されているか確認してください
- 「キチキチ予約システム」プラグインの管理画面 → 営業日カレンダーで登録します

### Payment confirmation troubleshooting

1. Confirm Webhook delivery is successful in the Stripe Dashboard.
2. Confirm `KKPAY_STRIPE_WEBHOOK_SECRET` contains the correct Webhook signing secret (`whsec_...`).
3. Confirm the WordPress REST API is available at `https://yoursite.com/wp-json/`.

### Mail troubleshooting

- WP Mail SMTP プラグインの **Email Test** 機能でテスト送信を実施してください
- Confirm `KKPAY_FROM_EMAIL` matches an email address registered on the mail server.

### 仮予約後に「期限切れ」と表示される

- wp-cron が正常に動作しているか確認してください
- Xserver では `disable_functions` の設定により wp-cron が遅延することがあります。
  その場合はサーバー側の cron（crontab）で以下を 1 分ごとに実行することを推奨します。

  ```bash
  */1 * * * * /usr/bin/php /path/to/wordpress/wp-cron.php
  ```

### Countdown timer troubleshooting

- Confirm JavaScript is enabled in the browser.
- Confirm `KKPAY_STRIPE_PUBLISHABLE_KEY` is set in `.env`.

---
## ファイル構成

```
kichikichi-early-reservation-system/
├── early-reservation-system.php     # プラグインエントリーポイント・定数定義
├── README.md                        # このファイル
├── assets/
│   ├── css/
│   │   ├── kkpay-form.css           # 予約フォーム・決済ページのスタイル
│   │   └── kkpay-mypage.css         # 予約照会・キャンセルページのスタイル
│   └── js/
│       ├── kkpay-form.js            # フォーム制御・Stripe.js 連携
│       └── kkpay-mypage.js          # 照会・キャンセル制御
├── includes/
│   ├── class-kkpay-activator.php    # DB テーブル作成（有効化/無効化フック）
│   ├── class-kkpay-calendar.php     # 営業カレンダー参照（読取専用）
│   ├── class-kkpay-hold.php         # 仮予約ロジック（トランザクション）
│   ├── class-kkpay-payment.php      # Stripe 決済・Webhook 処理
│   ├── class-kkpay-reservation.php  # 本予約 DB 操作・残席計算
│   ├── class-kkpay-cancel.php       # キャンセルロジック（返金なし）
│   ├── class-kkpay-mailer.php       # 5 言語メールテンプレート
│   ├── class-kkpay-cron.php         # wp-cron（ホールド自動開放）
│   └── class-kkpay-admin.php        # 管理画面（設定・予約リスト・CSV）
└── templates/
    ├── reservation-form.php         # 予約フォームテンプレート
    ├── payment-page.php             # 決済ページテンプレート
    └── my-reservation.php           # 予約照会・キャンセルテンプレート
```

---

## 対応言語

| コード | 言語 |
|---|---|
| `en` | English |
| `ja` | 日本語 |
| `ko` | 한국어 |
| `zh-CN` | 简体中文 |
| `zh-TW` | 繁體中文 |

フォームの表示言語はユーザーがセレクトボックスで選択します。
確認メール・キャンセルメールは予約時に選択された言語で送信されます。
