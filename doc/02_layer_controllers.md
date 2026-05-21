# Controllers 層

## 概要

Controllers 層は **WordPress AJAX / REST API のエントリポイント** です。  
HTTP リクエストを受け取り、処理を各層に委譲し、JSON レスポンスを返します。

---

## ファイル一覧

| ファイル | クラス名 | 担当エンドポイント |
|---------|---------|-----------------|
| `class-kkpay-hold-controller.php` | `KKPAY_Hold_Controller` | `kkpay_create_hold` |
| `class-kkpay-reservation-controller.php` | `KKPAY_Reservation_Controller` | `kkpay_get_available_slots`, `kkpay_check_reservation` |
| `class-kkpay-payment-controller.php` | `KKPAY_Payment_Controller` | `kkpay_create_payment_intent`, `kkpay_confirm_reservation`, REST Webhook |
| `class-kkpay-cancellation-controller.php` | `KKPAY_Cancellation_Controller` | `kkpay_cancel_reservation` |
| `class-kkpay-admin-controller.php` | `KKPAY_Admin_Controller` | `kkpay_load_admin_list`, `kkpay_export_csv` |

---

## Controllers 層がやること（責務）

```
1. check_ajax_referer()   → Nonce 検証（必須）
2. Validator::validate()  → 入力のサニタイズ・検証
3. Service メソッド呼び出し → ビジネスロジックに委譲
4. wp_send_json_success() / wp_send_json_error() → レスポンス返却
```

---

## Controllers 層がやってはいけないこと

| NG | 理由 | 代わりに |
|----|------|---------|
| `$wpdb` に直接アクセスする | DB 通信は Repository 層の責務 | `KKPAY_{Name}_Repository::メソッド()` を呼ぶ |
| `$_POST` を直接使ってビジネス判定する | 検証前のデータを信用してはいけない | Validator を通した戻り値を使う |
| ビジネスロジックを書く | Service 層の責務 | `KKPAY_{Name}_Service::メソッド()` を呼ぶ |
| Stripe API を直接叩く | Infrastructure 層の責務 | `KKPAY_Payment_Service` 経由 |
| エラーメッセージをハードコードする | 多言語対応が壊れる | `kkpay_msg($key, $lang)` を使う |

---

## コードテンプレート

新しい AJAX エンドポイントを追加するときはこの雛形を使います。

```php
class KKPAY_{機能名}_Controller {

    public static function ajax_{アクション名}() {
        // ① Nonce 検証（必ず最初に行う）
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        // ② バリデーション
        $data = KKPAY_{機能名}_Validator::validate( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        // ③ ビジネスロジックへ委譲
        $result = KKPAY_{機能名}_Service::処理メソッド( $data['key1'], $data['key2'] );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // ④ 成功レスポンス
        wp_send_json_success( array( 'key' => $result ) );
    }
}
```

---

## 実装例：HoldController の全体

```php
class KKPAY_Hold_Controller {

    public static function ajax_create_hold() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        // Validator はサニタイズ済みの配列 or WP_Error を返す
        $data = KKPAY_Hold_Validator::validate( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $lang = $data['lang'];

        // 営業日チェックは CalendarService の責務
        if ( ! KKPAY_Calendar_Service::is_accepting_reservations( $data['date'] ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'date_unavailable', $lang ) ) );
        }

        $valid_slots = KKPAY_Calendar_Service::get_available_slot_keys( $data['date'] );
        if ( empty( $valid_slots ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'closed', $lang ) ) );
        }
        if ( ! in_array( $data['slot'], $valid_slots, true ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'date_unavailable', $lang ) ) );
        }

        // ホールド作成は HoldService の責務
        $result = KKPAY_Hold_Service::create(
            $data['date'], $data['slot'], $data['num'],
            $data['name'], $data['email'], $lang
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'hold_token' => $result ) );
    }
}
```

---

## REST Webhook エンドポイントの特殊ルール

Stripe Webhook は `check_ajax_referer` が使えません。代わりに署名検証を行います。

```php
public static function handle_webhook( WP_REST_Request $request ) {
    $payload    = $request->get_body();
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $secret     = KKPAY_Stripe_Config::webhook_secret();

    // Nonce の代わりに Stripe 署名を検証する
    if ( ! KKPAY_Stripe_Client::verify_webhook_signature( $payload, $sig_header, $secret ) ) {
        return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 400 );
    }

    // ... イベント処理を Service に委譲
}
```

---

## AJAX アクションの登録場所

AJAX ハンドラの `add_action` は **エントリポイント（early-reservation-system.php）にまとめる**。  
Controller クラスのファイル内には書かない。

```php
// early-reservation-system.php
$kkpay_public_actions = array(
    'kkpay_create_hold' => array( 'KKPAY_Hold_Controller', 'ajax_create_hold' ),
    // ...
);

foreach ( $kkpay_public_actions as $action => $callback ) {
    add_action( 'wp_ajax_' . $action,        $callback );
    add_action( 'wp_ajax_nopriv_' . $action, $callback );
}
```

---

## よくある間違いと修正方法

### NG：Controller 内に SQL を書く

```php
// ❌ やってはいけない
public static function ajax_get_slots() {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}kkpay_holds ..." );
}

// ✅ 正しい
public static function ajax_get_slots() {
    $rows = KKPAY_Hold_Repository::find_by_token( $token );
}
```

### NG：Controller 内にキャンセル処理を書く

```php
// ❌ やってはいけない
public static function ajax_cancel() {
    global $wpdb;
    $wpdb->update( $wpdb->prefix . 'kkpay_reservations', ... );
    KKPAY_Email_Service::send_cancellation_confirmation( $reservation, 'none', 0 );
}

// ✅ 正しい
public static function ajax_cancel() {
    $result = KKPAY_Cancellation_Service::cancel( $reservation, $lang );
}
```
