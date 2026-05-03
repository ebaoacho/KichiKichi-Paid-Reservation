# Validators 層

## 概要

Validators 層は **外部入力（`$_POST`）の唯一の関門** です。  
「受け取った値が正しい形式か」を確認し、サニタイズ済みのクリーンな配列か `WP_Error` を返します。

ビジネスルールの検証（「この日付は予約受付中か」など）は **Service 層の責務** であり、Validator ではやりません。

---

## ファイル一覧

| ファイル | クラス名 | 検証するリクエスト |
|---------|---------|-----------------|
| `class-kkpay-hold-validator.php` | `KKPAY_Hold_Validator` | 仮予約作成リクエスト |
| `class-kkpay-payment-validator.php` | `KKPAY_Payment_Validator` | PaymentIntent 作成・予約確定リクエスト |
| `class-kkpay-reservation-validator.php` | `KKPAY_Reservation_Validator` | スロット一覧取得・予約照会リクエスト |
| `class-kkpay-cancellation-validator.php` | `KKPAY_Cancellation_Validator` | キャンセルリクエスト |

---

## Validators 層がやること（責務）

```
1. sanitize_*() 関数でサニタイズ
2. 必須項目の存在チェック
3. 型・形式の検証（メール形式、数値範囲、許可リストの照合）
4. クリーンな配列 or WP_Error を返す
```

---

## Validators 層がやってはいけないこと

| NG | 理由 | 代わりに |
|----|------|---------|
| DB に問い合わせる | DB アクセスは Repository 層の責務 | Service / Repository に任せる |
| 「この日付は営業日か」を判定する | ビジネスルールは Service 層の責務 | `KKPAY_Calendar_Service` に任せる |
| `wp_send_json_error()` を呼ぶ | レスポンス返却は Controller 層の責務 | `WP_Error` を返して Controller に判断させる |
| エラーメッセージをハードコードする | 多言語対応が壊れる | `kkpay_msg($key, $lang)` を使う |

---

## 戻り値の設計原則

**成功時** → サニタイズ済みの値が入った連想配列

```php
return array(
    'lang'  => 'ja',
    'date'  => '2025-06-01',
    'slot'  => 'slot_3',
    'num'   => 2,
    'name'  => '山田 太郎',
    'email' => 'yamada@example.com',
);
```

**失敗時** → `WP_Error`

```php
return new WP_Error( 'invalid_email', kkpay_msg( 'server_error', $lang ) );
```

Controller 側は `is_wp_error()` で判定します。戻り値の型が一致していれば Controller 側のコードは変更不要です。

---

## コードテンプレート

```php
class KKPAY_{機能名}_Validator {

    /**
     * @param array $input  $_POST 生配列
     * @return array|WP_Error  成功: クリーンな配列 / 失敗: WP_Error
     */
    public static function validate( array $input ) {
        $lang = self::sanitize_lang( $input['language'] ?? 'en' );

        // サニタイズ
        $field_a = sanitize_text_field( $input['field_a'] ?? '' );
        $field_b = sanitize_email( $input['field_b'] ?? '' );
        $field_c = intval( $input['field_c'] ?? 0 );

        // 必須チェック
        if ( ! $field_a ) {
            return new WP_Error( 'missing_field_a', kkpay_msg( 'server_error', $lang ) );
        }

        // 形式チェック
        if ( ! is_email( $field_b ) ) {
            return new WP_Error( 'invalid_email', kkpay_msg( 'server_error', $lang ) );
        }

        // 範囲チェック
        if ( $field_c < 1 || $field_c > 4 ) {
            return new WP_Error( 'out_of_range', kkpay_msg( 'max_people_exceeded', $lang ) );
        }

        return array(
            'lang'    => $lang,
            'field_a' => $field_a,
            'field_b' => $field_b,
            'field_c' => $field_c,
        );
    }

    private static function sanitize_lang( $lang ) {
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }
}
```

---

## 既存 Validator の検証項目一覧

### KKPAY_Hold_Validator

| フィールド | サニタイズ | 検証ルール |
|----------|-----------|-----------|
| `language` | 許可リスト照合 | `en/ja/ko/zh-CN/zh-TW` のいずれか（デフォルト: `en`） |
| `reservation_date` | `sanitize_text_field` | 空でないこと |
| `time_slot` | `sanitize_text_field` | `KKPAY_SLOT_TYPES` のキーに存在すること |
| `number_of_people` | `intval` | 1 以上 `KKPAY_MAX_PEOPLE`（4）以下 |
| `name` | `sanitize_text_field` | 空でないこと |
| `email` | `sanitize_email` | 空でなく、`is_email()` が true |

### KKPAY_Payment_Validator::validate_create_intent

| フィールド | サニタイズ | 検証ルール |
|----------|-----------|-----------|
| `hold_token` | `sanitize_text_field` | 空でないこと |

### KKPAY_Payment_Validator::validate_confirm

| フィールド | サニタイズ | 検証ルール |
|----------|-----------|-----------|
| `hold_token` | `sanitize_text_field` | 空でないこと |
| `payment_intent_id` | `sanitize_text_field` | 空でないこと |

### KKPAY_Reservation_Validator::validate_get_slots

| フィールド | サニタイズ | 検証ルール |
|----------|-----------|-----------|
| `language` | 許可リスト照合 | 許可言語のいずれか |
| `reservation_date` | `sanitize_text_field` | 空でないこと |

### KKPAY_Reservation_Validator::validate_check

| フィールド | サニタイズ | 検証ルール |
|----------|-----------|-----------|
| `language` | 許可リスト照合 | 許可言語のいずれか |
| `email` | `sanitize_email` | `is_email()` が true |

### KKPAY_Cancellation_Validator

| フィールド | サニタイズ | 検証ルール |
|----------|-----------|-----------|
| `language` | 許可リスト照合 | 許可言語のいずれか |
| `reservation_id` | `intval` | 0 より大きいこと |
| `email` | `sanitize_email` | 空でないこと |

---

## Validator が「やらない」ビジネス検証の例

以下はすべて **Service 層** で行います。Validator に書かないでください。

```
❌ Validator に書いてはいけない例

- 「この hold_token は DB に存在するか？」       → Service or Repository
- 「この日付は予約受付期間内か？」               → CalendarService
- 「このスロットは満席か？」                     → ReservationService
- 「この予約はすでにキャンセル済みか？」          → CancellationController or Service
- 「Stripe の hold_token は有効か？」            → PaymentService
```

---

## WordPress の sanitize 関数の使い分け

| 関数 | 使いどころ |
|------|----------|
| `sanitize_text_field()` | 一般的なテキスト（名前、スロットキーなど） |
| `sanitize_email()` | メールアドレス（`is_email()` と組み合わせて使う） |
| `intval()` | 整数（人数、ID など） |
| `absint()` | 正の整数のみ許可したい場合 |
| `sanitize_textarea_field()` | 複数行テキスト |
| `esc_url_raw()` | URL 文字列 |

`sanitize_text_field()` はタグを除去し XSS を防ぎますが、**値の存在チェックは別途行う**必要があります。
