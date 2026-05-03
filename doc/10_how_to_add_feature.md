# 新機能追加ガイド

新しい機能を追加するときの手順とチェックリストです。  
例として「クーポンコード機能」の追加を使って説明します。

---

## 全体の手順

```
1. どの層に何を書くかを設計する
2. Repository を作る（DB から独立しているので先に作れる）
3. Service を作る（ビジネスロジック）
4. Validator を作る（入力チェック）
5. Controller を作る（AJAX エンドポイント）
6. entry point に require_once と add_action を追加する
7. 動作確認
```

---

## ステップ 1：設計（何をどの層に置くか）

機能を追加する前に、以下を決めます。

**クーポン機能の例：**

| やること | 層 | ファイル |
|---------|-----|---------|
| DB テーブル `kkpay_coupons` の CRUD | Repositories | `class-kkpay-coupon-repository.php` |
| 割引率の計算・有効期限チェック | Services | `class-kkpay-coupon-service.php` |
| `coupon_code` の形式バリデーション | Validators | `class-kkpay-coupon-validator.php`（または既存 HoldValidator に追加） |
| AJAX エンドポイント `kkpay_apply_coupon` | Controllers | `class-kkpay-coupon-controller.php` |
| DB テーブル作成 | Activator | `class-kkpay-activator.php` に追記 |

---

## ステップ 2：Repository を作る

```php
// includes/Repositories/class-kkpay-coupon-repository.php

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class KKPAY_Coupon_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_coupons';
    }

    public static function find_by_code( $code ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE code = %s AND active = 1 LIMIT 1',
            $code
        ) );
    }

    public static function increment_used( $id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . ' SET used_count = used_count + 1 WHERE id = %d',
            (int) $id
        ) );
    }
}
```

---

## ステップ 3：Service を作る

```php
// includes/Services/class-kkpay-coupon-service.php

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class KKPAY_Coupon_Service {

    /**
     * クーポンコードを検証し、割引後の金額を返す
     *
     * @param string $code    クーポンコード
     * @param string $lang    言語
     * @return array|WP_Error 成功: ['discount'=>500, 'final_amount'=>2500] / 失敗: WP_Error
     */
    public static function apply( $code, $lang ) {
        $coupon = KKPAY_Coupon_Repository::find_by_code( $code );

        if ( ! $coupon ) {
            return new WP_Error( 'invalid_coupon', kkpay_msg( 'invalid_coupon', $lang ) );
        }

        // 有効期限チェック
        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );
        $exp = new DateTimeImmutable( $coupon->expires_at, $tz );

        if ( $now > $exp ) {
            return new WP_Error( 'coupon_expired', kkpay_msg( 'coupon_expired', $lang ) );
        }

        $discount     = (int) $coupon->discount_amount;
        $final_amount = max( 0, KKPAY_AMOUNT - $discount );

        return array(
            'coupon_id'    => (int) $coupon->id,
            'discount'     => $discount,
            'final_amount' => $final_amount,
        );
    }
}
```

---

## ステップ 4：Validator を作る

既存の Validator にメソッドを追加するか、新しいクラスを作ります。

```php
// includes/Validators/class-kkpay-coupon-validator.php

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class KKPAY_Coupon_Validator {

    public static function validate( array $input ) {
        $lang = self::sanitize_lang( $input['language'] ?? 'en' );
        $code = sanitize_text_field( $input['coupon_code'] ?? '' );

        if ( ! $code ) {
            return new WP_Error( 'missing_code', kkpay_msg( 'server_error', $lang ) );
        }
        // 形式チェック（英数字 6〜12 文字）
        if ( ! preg_match( '/^[A-Z0-9]{6,12}$/', strtoupper( $code ) ) ) {
            return new WP_Error( 'invalid_code_format', kkpay_msg( 'server_error', $lang ) );
        }

        return array( 'lang' => $lang, 'code' => strtoupper( $code ) );
    }

    private static function sanitize_lang( $lang ) {
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }
}
```

---

## ステップ 5：Controller を作る

```php
// includes/Controllers/class-kkpay-coupon-controller.php

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class KKPAY_Coupon_Controller {

    public static function ajax_apply_coupon() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Coupon_Validator::validate( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $result = KKPAY_Coupon_Service::apply( $data['code'], $data['lang'] );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }
}
```

---

## ステップ 6：エントリポイントに追加する

`early-reservation-system.php` を編集します。変更箇所は 2 か所です。

### require_once を追加（依存順を守る）

```php
// Repositories（DB通信層）
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-calendar-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-hold-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-reservation-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-cancellation-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-coupon-repository.php'; // ← 追加

// Services（ビジネスロジック層）
// ... 既存 ...
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-coupon-service.php'; // ← 追加

// Validators（バリデーション層）
// ... 既存 ...
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-coupon-validator.php'; // ← 追加

// Controllers（リクエスト受付・レスポンス返却層）
// ... 既存 ...
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-coupon-controller.php'; // ← 追加
```

### AJAX ハンドラを登録する

```php
$kkpay_public_actions = array(
    // ... 既存 ...
    'kkpay_apply_coupon' => array( 'KKPAY_Coupon_Controller', 'ajax_apply_coupon' ), // ← 追加
);
```

---

## ステップ 7：DB テーブルを追加する（必要な場合）

`includes/class-kkpay-activator.php` の `create_tables()` に追加します。

```php
private static function create_tables() {
    // ... 既存のテーブル作成 ...

    // kkpay_coupons（新規追加）
    $coupons = $wpdb->prefix . 'kkpay_coupons';
    dbDelta( "CREATE TABLE {$coupons} (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code            VARCHAR(20)     NOT NULL,
        discount_amount INT             NOT NULL DEFAULT 0,
        expires_at      DATETIME        NOT NULL,
        used_count      INT             NOT NULL DEFAULT 0,
        active          TINYINT(1)      NOT NULL DEFAULT 1,
        created_at      DATETIME        NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY code (code)
    ) {$charset};" );
}
```

**注意：** `dbDelta()` は `CREATE TABLE IF NOT EXISTS` ではなく `CREATE TABLE` で記述します。  
`dbDelta()` 内部で存在チェックと差分適用を行います。

---

## 既存機能を変更する場合のガイドライン

### ビジネスルールの変更（例：キャンセル期限を 48 時間前に変更）

```php
// KKPAY_Cancellation_Service::cancel() 内
// ❌ ハードコード
$cutoff = $date->modify( '-1 day' );

// ✅ 定数化して管理
$cutoff = $date->modify( '-' . KKPAY_CANCEL_HOURS_BEFORE . ' hours' );
// early-reservation-system.php で define( 'KKPAY_CANCEL_HOURS_BEFORE', 48 );
```

### メールテンプレートの変更

`includes/Services/class-kkpay-email-service.php` の `$bodies` 配列を編集します。  
5 言語すべてを必ず更新してください。

### スロット構成の変更（例：スロットを増やす）

`early-reservation-system.php` の `KKPAY_SLOT_TYPES` と `KKPAY_SLOT_LABELS` を編集します。

```php
define( 'KKPAY_SLOT_TYPES', array(
    'slot_1' => 'lunch',
    // ...
    'slot_7' => 'dinner',  // ← 追加
) );
```

---

## チェックリスト

新機能追加後に確認する項目です。

### コード確認

- [ ] Controller で `check_ajax_referer()` を呼んでいる
- [ ] `$_POST` を直接使わず Validator を通している
- [ ] DB アクセスが Repository のみ（Service・Controller に `$wpdb` を直書きしていない）
- [ ] ビジネスロジックが Service に集まっている
- [ ] エラーは `WP_Error` で返している
- [ ] エラーメッセージが `kkpay_msg()` を使っている（ハードコードしていない）
- [ ] 多言語対応が必要な場合、5 言語すべてに翻訳を追加した
- [ ] `require_once` を依存順に追加した
- [ ] `add_action` をエントリポイントに追加した

### 動作確認

- [ ] 正常系が動作する
- [ ] 必須項目が空の場合に適切なエラーが返る
- [ ] Nonce が不正な場合に拒否される
- [ ] 管理者でないユーザーが管理者専用エンドポイントを呼べない
