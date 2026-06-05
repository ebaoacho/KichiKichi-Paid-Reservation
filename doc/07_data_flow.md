# データフロー詳細

ユーザーの操作からデータベースへの反映・メール送信までの流れを、  
各層を通るデータとともに詳細に記述します。

---

## フロー 1：スロット一覧の取得

ユーザーが日付を選択したとき。

```
[ブラウザ]
  POST admin-ajax.php
  action=kkpay_get_available_slots
  reservation_date=2025-06-01
  language=ja
  nonce=xxx
        │
        ▼
[KKPAY_Reservation_Controller::ajax_get_available_slots()]
  check_ajax_referer('kkpay_nonce', 'nonce')
        │
        ▼
[KKPAY_Reservation_Validator::validate_get_slots($_POST)]
  サニタイズ: date, lang
  → { lang: 'ja', date: '2025-06-01' }
        │
        ▼
[KKPAY_Calendar_Service::is_accepting_reservations('2025-06-01')]
  ├─ プレミアムモード（accepted_dates にレコードあり）:
  │    KKPAY_Accepted_Dates_Repository::is_date_enabled('2025-06-01')
  │    → enabled=1 のレコードがなければ false（受付対象外）
  └─ 通常モード（accepted_dates が空）:
       対象日が本日〜3日後の範囲内 かつ 3日前13:00 JST 以降なら true
  → false なら 'not_yet_open' エラーを返す
        │
        ▼
[KKPAY_Calendar_Service::get_bookable_slot_keys('2025-06-01')]
  → KKPAY_Calendar_Repository::find_by_date('2025-06-01')
  → { lunch:1, dinner:1, premium:1 }
  ├─ premium=0 または営業枠なしなら空配列
  └─ accepted_dates がある場合は enabled=1 スロットとの積集合
  → ['slot_1','slot_2','slot_3','slot_4','slot_5','slot_6']
        │
        ▼
[KKPAY_Reservation_Service::build_slot_list(date, slot_keys, lang)]
  各スロットに対して:
  → KKPAY_Reservation_Repository::sum_people_for_slot(date, slot)
  → KKPAY_Hold_Repository::sum_people_for_slot(date, slot)
  → remaining = 8 - confirmed - held
  → [{ key:'slot_3', label:'ご来店: 4:40 PM...', remaining:6, available:true }, ...]
        │
        ▼
[ブラウザ]
  { success: true, data: { slots: [...] } }
```

---

## フロー 2：仮予約（ホールド）の作成

ユーザーが「次へ（お支払いへ）」ボタンを押したとき。

```
[ブラウザ]
  POST admin-ajax.php
  action=kkpay_create_hold
  reservation_date=2025-06-01, time_slot=slot_3
  number_of_people=2, name=山田 太郎, email=yamada@example.com
  language=ja, nonce=xxx
        │
        ▼
[KKPAY_Hold_Controller::ajax_create_hold()]
  check_ajax_referer()
        │
        ▼
[KKPAY_Hold_Validator::validate($_POST)]
  サニタイズ・形式チェック
  → { lang:'ja', date:'2025-06-01', slot:'slot_3', num:2,
      name:'山田 太郎', email:'yamada@example.com' }
        │
        ▼
[KKPAY_Calendar_Service::is_accepting_reservations('2025-06-01')]
  ├─ プレミアムモード: is_date_enabled() で enabled=1 レコードの存在確認
  └─ 通常モード: 3日前13:00 JST 以降かどうかを時刻比較
[KKPAY_Calendar_Service::get_bookable_slot_keys('2025-06-01')]
  ├─ 営業日かつプレミアム予約可能日であることを確認
  └─ accepted_dates がある場合は enabled=1 スロットに絞り込み
  → slot_3 が有効リストに含まれることを確認
        │
        ▼
[KKPAY_Hold_Service::create(date, slot, 2, name, email, 'ja')]
  global $wpdb;
  START TRANSACTION;
    ┌─ KKPAY_Reservation_Repository::sum_people_for_slot_with_lock(date, slot)
    │    SELECT SUM(number_of_people) ... FOR UPDATE → 2（人）
    └─ KKPAY_Hold_Repository::sum_people_for_slot_with_lock(date, slot)
         SELECT SUM(number_of_people) ... FOR UPDATE → 0（人）
  2 + 0 + 2 = 4 ≦ 8 → OK
  hold_token = bin2hex(random_bytes(32))  → 64 文字のランダム文字列
  KKPAY_Hold_Repository::insert( { reservation_date, time_slot, ... expires_at: NOW()+5min } )
  COMMIT;
  → hold_token を返す
        │
        ▼
[ブラウザ]
  { success: true, data: { hold_token: 'abc123...' } }
  → /payment/?hold_token=abc123...&lang=ja にリダイレクト
```

---

## フロー 3：決済（PaymentIntent 作成 → カード確認 → 予約確定）

決済ページで「お支払い」ボタンを押したとき。

### ステップ 3-1：PaymentIntent 作成

```
[ブラウザ] POST kkpay_create_payment_intent (hold_token, nonce)
        │
        ▼
[KKPAY_Payment_Controller::ajax_create_payment_intent()]
  → KKPAY_Payment_Validator::validate_create_intent($_POST)
  → KKPAY_Hold_Repository::find_by_token(hold_token)
      （期限切れなら 'hold_expired' エラー）
  → KKPAY_Payment_Service::create_payment_intent($hold)
      → KKPAY_Stripe_Client::request('POST', '/v1/payment_intents', {
          amount: 3000, currency: 'jpy',
          metadata: { hold_token, reservation_date, time_slot, email }
        })
      → { id: 'pi_xxx', client_secret: 'pi_xxx_secret_yyy' }
        │
        ▼
[ブラウザ]
  { client_secret: 'pi_xxx_secret_yyy', payment_intent_id: 'pi_xxx' }
```

### ステップ 3-2：Stripe.js によるカード決済（ブラウザのみ）

```
[ブラウザ]
  stripe.confirmCardPayment(clientSecret, { payment_method: { card: cardElement } })
  → Stripe サーバーが直接カードを処理
  → 成功: paymentIntent.status === 'succeeded'
```

### ステップ 3-3：予約確定

```
[ブラウザ] POST kkpay_confirm_reservation (hold_token, payment_intent_id, nonce)
        │
        ▼
[KKPAY_Payment_Controller::ajax_confirm_reservation()]
  → KKPAY_Payment_Validator::validate_confirm($_POST)
  → KKPAY_Hold_Repository::find_by_token(hold_token)
      （期限切れ時: find_by_payment_intent で既存確定チェック → 存在すれば返す）
  → KKPAY_Payment_Service::confirm($hold, pi_id)
      ① KKPAY_Stripe_Client::request('GET', '/v1/payment_intents/pi_xxx')
         → status === 'succeeded' を確認
      ② KKPAY_Reservation_Repository::find_by_payment_intent(pi_id)
         → null（まだ確定していない）
      ③ KKPAY_Reservation_Service::create_from_hold($hold, pi_id, charge_id, 'paid')
         → KKPAY_Reservation_Repository::insert({ ... payment_status:'paid' })
         → reservation_id = 42
      ④ KKPAY_Hold_Repository::delete_by_token(hold_token)
      ⑤ KKPAY_Email_Service::send_booking_confirmation($reservation)
      ⑥ return $reservation（stdClass）
        │
        ▼
[ブラウザ]
  { reservation_id:42, reservation_date:'2025-06-01', ... }
  → 予約完了画面を表示
```

---

## フロー 4：Stripe Webhook（フォールバック確定）

ネットワーク障害などで `confirm_reservation` が失敗した場合の安全網。

```
[Stripe サーバー]
  POST /wp-json/kkpay/v1/webhook
  Stripe-Signature: t=xxx,v1=yyy
  body: { type:'payment_intent.succeeded', data:{ object:{ id:'pi_xxx', ... } } }
        │
        ▼
[KKPAY_Payment_Controller::handle_webhook()]
  → KKPAY_Stripe_Client::verify_webhook_signature(payload, sig, secret)
      （署名が不正なら 400 を返す）
  → json_decode(body)
  → type === 'payment_intent.succeeded'
  → KKPAY_Payment_Service::handle_payment_intent_succeeded($pi)
      ① find_by_payment_intent('pi_xxx') → null（confirm が失敗していた場合）
      ② find_by_token_any(hold_token)   → hold レコードを取得（期限切れでも OK）
      ③ create_from_hold($hold, pi_id, charge_id, 'paid')
      ④ delete_by_token(hold_token)
      ⑤ send_booking_confirmation($reservation)
        │
        ▼
[Stripe サーバー]
  200 OK { received: true }
```

**冪等性の保証：**  
`confirm_reservation` が先に成功して予約が確定していた場合、  
Webhook の `find_by_payment_intent` で既存レコードが見つかり、重複 INSERT は発生しません。

---

## フロー 5：予約照会

「マイ予約」ページでメールアドレスを入力したとき。

```
[ブラウザ] POST kkpay_check_reservation (email, language, nonce)
        │
        ▼
[KKPAY_Reservation_Controller::ajax_check_reservation()]
  → KKPAY_Reservation_Validator::validate_check($_POST)
  → KKPAY_Reservation_Repository::find_by_email(email)
      （見つからなければ 'reservation_not_found'）
  → KKPAY_Reservation_Service::build_check_data($reservation, $lang)
      deadline = 予約日 00:00 - 1日
      can_cancel = cancelled_at IS NULL AND status != 'pending' AND now < deadline
        │
        ▼
[ブラウザ]
  { reservation_id:42, can_cancel:true, cancel_deadline:'2025-05-31 00:00', ... }
```

---

## フロー 6：キャンセル

「キャンセルする」ボタンを押したとき。

```
[ブラウザ] POST kkpay_cancel_reservation (reservation_id, email, language, nonce)
        │
        ▼
[KKPAY_Cancellation_Controller::ajax_cancel_reservation()]
  → KKPAY_Cancellation_Validator::validate($_POST)
  → KKPAY_Reservation_Repository::find_by_id(reservation_id)
      メール一致・未キャンセル・paid 状態の確認
  → KKPAY_Cancellation_Service::cancel($reservation, $lang)
      ① refund_status = 'none', refund_amount = 0, stripe_refund_id = null
      ② KKPAY_Cancellation_Repository::insert({ reservation_id, refund_status:'none', refund_amount:0, ... })
      ③ KKPAY_Reservation_Repository::update_cancelled(id, now, 現在の payment_status)
      ④ KKPAY_Email_Service::send_cancellation_confirmation($reservation, 'none', 0)
      ⑤ return { refund_status:'none', refund_amount:0, message:'...' }
        │
        ▼
[ブラウザ]
  { message:'予約をキャンセルしました。返金はありません。' }
```

---

## エラー時のデータフロー

どの層でエラーが発生しても、Controller で `WP_Error` を受け取り `wp_send_json_error` を返します。

```
Service::method() → WP_Error('capacity_exceeded', 'このスロットは満席です。')
        │
        ▼
Controller:
  if ( is_wp_error( $result ) ) {
      wp_send_json_error( array( 'message' => $result->get_error_message() ) );
  }
        │
        ▼
[ブラウザ]
  { success: false, data: { message: 'このスロットは満席です。' } }
```
