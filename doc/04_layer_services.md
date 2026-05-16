# Services 層

## 概要

Services 層は **ビジネスロジックの中心** です。  
「何をすべきか」の判断・処理の順序・複数のリポジトリやインフラへの協調呼び出しはここに書きます。

Controller はリクエストを受けるだけ、Repository は SQL を発行するだけ。  
その間の「頭脳」として機能するのが Service です。

---

## ファイル一覧

| ファイル | クラス名 | 責務 |
|---------|---------|------|
| `class-kkpay-calendar-service.php` | `KKPAY_Calendar_Service` | 予約受付可否の判定・有効スロットの算出 |
| `class-kkpay-hold-service.php` | `KKPAY_Hold_Service` | 仮予約作成（トランザクション管理） |
| `class-kkpay-reservation-service.php` | `KKPAY_Reservation_Service` | 残席計算・予約レコード作成・照会データ整形 |
| `class-kkpay-payment-service.php` | `KKPAY_Payment_Service` | Stripe PaymentIntent 処理・Webhook ハンドラ |
| `class-kkpay-cancellation-service.php` | `KKPAY_Cancellation_Service` | キャンセル記録・キャンセルメール送信（返金なし） |
| `class-kkpay-email-service.php` | `KKPAY_Email_Service` | 5 言語メールテンプレートと送信 |

---

## Services 層がやること（責務）

```
1. ビジネスルールの判定（キャンセル可否など）
2. 複数の Repository / Infrastructure を協調して呼ぶ
3. トランザクション管理（START TRANSACTION / COMMIT / ROLLBACK）
4. 成功時は結果値、失敗時は WP_Error を返す
```

---

## Services 層がやってはいけないこと

| NG | 理由 | 代わりに |
|----|------|---------|
| `$_POST` や `$_GET` に直接触れる | 入力は Controller / Validator が担う | 引数として受け取る |
| `wp_send_json_*()` を呼ぶ | レスポンス返却は Controller の責務 | 結果を return する |
| `check_ajax_referer()` を呼ぶ | セキュリティ検証は Controller の責務 | Controller に任せる |
| HTML を出力する | 表示は templates の責務 | HTML を返すなら文字列として return |

---

## 各 Service の詳細

### KKPAY_Calendar_Service

営業日・受付期間の判定ロジックを一元管理します。

```php
// 日付が受付期間内か（JST 固定）
KKPAY_Calendar_Service::is_accepting_reservations( '2025-06-01' ); // bool

// その日に予約可能なスロットキー一覧
KKPAY_Calendar_Service::get_available_slot_keys( '2025-06-01' ); // string[]
```

**受付判定は 2 モードあり、`kkpay_accepted_dates` テーブルの有無で自動的に切り替わります。**

#### 通常モード（`kkpay_accepted_dates` にレコードなし）

時刻ベースの受付判定を行います。

- 対象日が「本日〜`KKPAY_ACCEPT_DAYS_BEFORE` 日後」の範囲内
- かつ「対象日の `KKPAY_ACCEPT_DAYS_BEFORE` 日前の **`KKPAY_ACCEPT_HOUR_JST` 時** 以降」

例：5/10 の予約 → 5/7（3 日前）の **13:00 JST** から受付開始

`KKPAY_ACCEPT_HOUR_JST` は**通常モード専用**の定数です。プレミアムモードでは参照されません。

#### プレミアムモード（`kkpay_accepted_dates` にレコードあり）

管理者が登録した日程・スロットのみ受付します。

- `enabled = 1` のレコードがある日程だけ `is_accepting_reservations()` が `true` を返す
- 受付開始は**対象日の 3 日前 0:00 JST**（時刻ベースのルールは適用されない）
- `get_available_slot_keys()` は `enabled = 1` のスロットのみを返す

例：5/10 の予約が `accepted_dates` に登録済み → 5/7 **0:00 JST** から受付開始

---

### KKPAY_Hold_Service

5 分間の仮予約（ホールド）を作成します。  
**競合する同時予約を防ぐため、SELECT FOR UPDATE を使ったトランザクションが必須です。**

```php
$hold_token = KKPAY_Hold_Service::create( $date, $slot, $num, $name, $email, $lang );
// 成功: 64 文字の hold_token 文字列
// 失敗: WP_Error（capacity_exceeded など）
```

**トランザクション処理の流れ：**

```
START TRANSACTION
  ↓
SELECT SUM(people) FROM reservations WHERE ... FOR UPDATE  （確定済み人数）
  ↓
SELECT SUM(people) FROM holds WHERE ... FOR UPDATE          （ホールド中人数）
  ↓
合計 + 申込人数 > MAX_CAPACITY なら ROLLBACK → WP_Error
  ↓
INSERT INTO holds ...
  ↓
COMMIT → hold_token を返す
```

> FOR UPDATE は必ず **同じトランザクション内** で実行されます。  
> `sum_people_for_slot_with_lock()` を単独で呼ぶことは意味がありません。

---

### KKPAY_Reservation_Service

```php
// 有効スロットの残席情報リストを返す
KKPAY_Reservation_Service::build_slot_list( $date, $slot_keys, $lang );
// → [['key'=>'slot_3', 'label'=>'...', 'remaining'=>6, 'available'=>true], ...]

// 残席数を返す（確定済み + ホールド中を差し引く）
KKPAY_Reservation_Service::get_remaining_capacity( $date, $slot );
// → int（0 以上）

// ホールドから予約レコードを作成（冪等性あり）
KKPAY_Reservation_Service::create_from_hold( $hold, $pi_id, $charge_id, 'paid' );
// 成功: 予約 ID (int) / 失敗: WP_Error

// 予約照会レスポンス用のデータ配列を作成
KKPAY_Reservation_Service::build_check_data( $reservation, $lang );
// → ['can_cancel'=>true, 'cancel_deadline'=>'2025-05-31 00:00', ...]
```

**`create_from_hold` の冪等性：**  
UNIQUE KEY 違反（同じ `stripe_payment_intent_id` の 2 重 INSERT）が発生した場合は、  
既存レコードの ID を返して正常終了します。Webhook との二重実行に対応しています。

---

### KKPAY_Payment_Service

Stripe の PaymentIntent 処理を担います。

```php
// PaymentIntent を Stripe に作成する
KKPAY_Payment_Service::create_payment_intent( $hold );
// → Stripe API レスポンス配列 or WP_Error

// クライアント側決済完了後の予約確定
KKPAY_Payment_Service::confirm( $hold, $pi_id );
// 成功: 予約レコード (stdClass) / 失敗: WP_Error

// Webhook イベント処理
KKPAY_Payment_Service::handle_payment_intent_succeeded( $pi );
KKPAY_Payment_Service::handle_charge_refunded( $charge );
```

**confirm() の処理順序（重要）：**

```
1. Stripe API で payment_intent のステータスを確認 → succeeded でなければエラー
2. DB に同じ PI ID の予約が存在しないか冪等性チェック
3. create_from_hold() で予約レコードを INSERT
4. Hold を DELETE
5. 確認メールを送信
6. 予約レコードを返す
```

---

### KKPAY_Cancellation_Service

```php
$result = KKPAY_Cancellation_Service::cancel( $reservation, $lang );
// 成功: ['refund_status'=>'none', 'refund_amount'=>0, 'message'=>'...']
// 失敗: WP_Error
```

**キャンセルポリシー：**

```
キャンセルしても返金なし
Stripe /v1/refunds は呼び出さない
refund_status = 'none'
refund_amount = 0
stripe_refund_id = null
```

**キャンセル処理の順序：**

```
1. cancellations テーブルに refund_status='none' / refund_amount=0 の履歴を INSERT
2. reservations の cancelled_at を UPDATE（payment_status はキャンセル前の値を維持）
3. キャンセル確認メールを送信
```

---

### KKPAY_Email_Service

5 言語対応のメールテンプレートと送信を担当します。

```php
// 予約確定メール
KKPAY_Email_Service::send_booking_confirmation( $reservation );

// キャンセル確認メール
KKPAY_Email_Service::send_cancellation_confirmation( $reservation, 'none', 0 );
```

メールの「From」は `KKPAY_Email_Config` 経由で `KKPAY_FROM_NAME` / `KKPAY_FROM_EMAIL` から読み取ります。  
テンプレートを変更する場合はこのファイルの `$bodies` 配列を編集してください。

---

## Service メソッドの戻り値設計

| 戻り値 | ケース |
|-------|-------|
| スカラー値（`string`, `int`） | 単純な成功結果（`hold_token`, `reservation_id`） |
| 連想配列 | 複数の値を返す成功結果（スロットリストなど） |
| `stdClass`（DB レコード） | 予約レコードそのものを返す場合 |
| `WP_Error` | 失敗時は **必ず** `WP_Error` を返す |

`null` や `false` で失敗を表現しないでください。  
呼び出し側が `is_wp_error()` で統一的に処理できます。

---

## トランザクションの書き方

トランザクションは **Service 層のみ** で管理します。Repository に書かない。

```php
// ✅ 正しいパターン
public static function create( ... ) {
    global $wpdb;

    $wpdb->query( 'START TRANSACTION' );

    // Repository の _with_lock 版を呼ぶ
    $confirmed = KKPAY_Reservation_Repository::sum_people_for_slot_with_lock( $date, $slot );
    $held      = KKPAY_Hold_Repository::sum_people_for_slot_with_lock( $date, $slot );

    if ( 条件 ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'capacity_exceeded', kkpay_msg( 'capacity_exceeded', $lang ) );
    }

    $ok = KKPAY_Hold_Repository::insert( $data );
    if ( ! $ok ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'server_error', kkpay_msg( 'server_error', $lang ) );
    }

    $wpdb->query( 'COMMIT' );
    return $hold_token;
}
```
