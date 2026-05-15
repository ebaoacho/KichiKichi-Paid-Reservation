# Infrastructure 層

## 概要

Infrastructure 層は **外部サービス（Stripe など）との通信を抽象化** する層です。  
「Stripe にどうリクエストするか」という技術的詳細をここに閉じ込め、  
Service 層は「Stripe API を呼ぶ」ことだけを意識すれば済むようにします。

---

## ファイル一覧

| ファイル | クラス名 | 担当 |
|---------|---------|------|
| `class-kkpay-stripe-client.php` | `KKPAY_Stripe_Client` | Stripe API への HTTP リクエスト + Webhook 署名検証 |

---

## KKPAY_Stripe_Client

### メソッド一覧

| メソッド | 説明 |
|---------|------|
| `request( $method, $path, $body )` | Stripe API にリクエストを送信する |
| `verify_webhook_signature( $payload, $sig_header, $secret )` | Webhook の署名を検証する |

---

### `request()` の使い方

```php
// GET リクエスト
$pi = KKPAY_Stripe_Client::request( 'GET', '/v1/payment_intents/' . $pi_id );

// POST リクエスト（$body は連想配列）
$pi = KKPAY_Stripe_Client::request( 'POST', '/v1/payment_intents', array(
    'amount'   => 1300,
    'currency' => 'usd',
) );

// 戻り値
if ( is_wp_error( $pi ) ) {
    // Stripe がエラーを返した、またはネットワークエラー
    $message = $pi->get_error_message();
} else {
    // 成功時は Stripe API レスポンスの連想配列
    $status = $pi['status'];
}
```

**注意点：**
- シークレットキーは `KKPAY_Stripe_Config::secret_key()` から自動で読み取ります。`request()` の引数に渡す必要はありません。
- タイムアウトは 30 秒（変更する場合はこのクラスを修正）。
- HTTP 2xx 以外のレスポンスは自動で `WP_Error` に変換されます。

---

### Stripe API のパスの書き方

```php
// PaymentIntent 作成
'/v1/payment_intents'

// PaymentIntent 取得（ID が含まれる場合は rawurlencode で安全にエンコード）
'/v1/payment_intents/' . rawurlencode( $pi_id )

// 通常のキャンセル処理では返金 API は使用しない

// Charge 取得
'/v1/charges/' . rawurlencode( $charge_id )
```

---

### `verify_webhook_signature()` の仕様

Stripe が送信するリクエストヘッダー `Stripe-Signature` の値を検証します。

```
Stripe-Signature: t=1609459200,v1=abc123...,v1=def456...
```

| 要素 | 内容 |
|------|------|
| `t` | Unix タイムスタンプ |
| `v1` | HMAC-SHA256 署名（複数ある場合は 1 つでも一致すれば OK） |

**タイムスタンプ許容誤差：±300 秒**  
これより古い署名はリプレイ攻撃とみなして拒否します。

```php
$ok = KKPAY_Stripe_Client::verify_webhook_signature(
    $request->get_body(),
    $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '',
    KKPAY_Stripe_Config::webhook_secret()
);
```

---

## 新しい外部サービスを追加する場合

例：PayPay を追加する場合

```
includes/
  Infrastructure/
    class-kkpay-stripe-client.php  （既存）
    class-kkpay-paypay-client.php  （新規追加）
```

クラス命名規則：`KKPAY_{サービス名}_Client`

`request()` に相当するメソッドを持たせ、成功時は配列・失敗時は `WP_Error` を返す設計にします。  
これにより Service 層は `is_wp_error()` で統一的にエラーを処理できます。

---

## なぜ Infrastructure 層を分けるのか

### テスト・差し替えが容易になる

`KKPAY_Stripe_Client` を差し替えたいとき（例：テスト環境で Stripe モックを使いたいとき）、  
このクラスだけを修正すれば Service 層は変更不要です。

### Stripe SDK のバージョンアップに強い

Stripe API のバージョンが変わっても、このファイルだけを修正します。  
現在の API バージョンはこのファイルの以下の行で管理しています。

```php
'Stripe-Version' => '2023-10-16',
```

### 認証情報の管理場所が明確

Stripe シークレットキーを読み取る場所は `KKPAY_Stripe_Client::request()` の 1 箇所のみです。  
他のクラスが Stripe の環境変数や `get_option('kkpay_stripe_sk')` を直接呼ぶことは禁止します。Stripe 認証情報は `KKPAY_Stripe_Config` に集約します。
