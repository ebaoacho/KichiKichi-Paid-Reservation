# Stripe 連携仕様

## 概要

本プラグインは Stripe の **PaymentIntent API** を使用します。  
カード情報は Stripe.js がブラウザ上で直接 Stripe サーバーに送るため、  
サーバー（WordPress）はカード番号を一切受け取りません（PCI DSS スコープ外）。
## Settings (environment)
| Environment variable | Content | Where to set |
|-------------|------|------------------|
| `KKPAY_STRIPE_PUBLISHABLE_KEY` | Publishable key (`pk_live_xxx`) | environment variable / wp-config.php constant |
| `KKPAY_STRIPE_SECRET_KEY` | Secret key (`sk_live_xxx`) | environment variable / wp-config.php constant |
| `KKPAY_STRIPE_WEBHOOK_SECRET` | Webhook signing secret (`whsec_xxx`) | environment variable / wp-config.php constant |

Use `pk_test_` / `sk_test_` values in the environment for test mode. No code change is needed.
---


## 使用する Stripe API

| エンドポイント | 用途 |
|-------------|------|
| `POST /v1/payment_intents` | PaymentIntent 作成 |
| `GET /v1/payment_intents/{id}` | PaymentIntent のステータス確認 |
| `POST /v1/refunds` | 使用しない（通常キャンセル時の返金なし） |

**API バージョン：** `2023-10-16`  
（`includes/Infrastructure/class-kkpay-stripe-client.php` の `Stripe-Version` ヘッダーで管理）

---

## PaymentIntent フロー詳細

### なぜ PaymentIntent を使うか

- 3D セキュア（認証フロー）に自動対応できる
- Stripe 側で決済の状態管理（`requires_payment_method` → `processing` → `succeeded`）が行われる
- Webhook と組み合わせることで決済の確実性が高まる

### フロー

```
[PHP] POST /v1/payment_intents
      amount=3000, currency=jpy
      automatic_payment_methods[enabled]=true
      metadata[hold_token]=abc123
      metadata[reservation_date]=2025-06-01
      metadata[time_slot]=slot_3
      metadata[email]=yamada@example.com
        │
        ▼
[Stripe] PaymentIntent 作成 → client_secret を返す

[JavaScript] stripe.confirmCardPayment(client_secret, { card: cardElement })
        │
        ▼
[Stripe] カード決済処理 → status: 'succeeded'

[PHP] GET /v1/payment_intents/{id}
      → status === 'succeeded' を確認してから予約確定
```

### metadata の使い方

PaymentIntent の `metadata` に予約情報を埋め込む理由：

- **Webhook がこの情報を使う。** `payment_intent.succeeded` が届いたとき、metadata の `hold_token` から hold レコードを探して予約を確定させます。
- Stripe ダッシュボードで「この PaymentIntent はどの予約か」が一目でわかります。

---

## Webhook の仕様

### エンドポイント

```
POST /wp-json/kkpay/v1/webhook
```

このエンドポイントを **Stripe ダッシュボードの Webhook に登録**してください。

The Webhook URL is `https://yoursite.com/wp-json/kkpay/v1/webhook`.

### 処理するイベント

| イベント | 処理内容 |
|---------|---------|
| `payment_intent.succeeded` | 予約確定（`confirm_reservation` が失敗した場合のフォールバック） |
| `charge.refunded` | Stripe ダッシュボード等で外部返金された場合に `payment_status` を `refunded` に更新 |

それ以外のイベントは 200 を返して無視します。

### 署名検証

Stripe は `Stripe-Signature` ヘッダーに HMAC-SHA256 署名を付与します。

```
Stripe-Signature: t=1609459200,v1=abc123def456...
```

**検証手順：**
1. `t` タイムスタンプが ±300 秒以内か確認（リプレイ攻撃対策）
2. `t + "." + payload` の HMAC-SHA256 を `whsec_xxx` で計算
3. ヘッダー内の `v1` 署名と一致するか確認

検証が失敗した場合は即座に 400 を返します。

### Webhook の冪等性

同じ `payment_intent.succeeded` が複数回届く場合があります（Stripe の再送機能）。  
`find_by_payment_intent()` で既に確定済みかチェックし、重複 INSERT を防いでいます。

```
イベント 1 回目 → find_by_payment_intent() → null → INSERT 実行
イベント 2 回目 → find_by_payment_intent() → 存在する → 何もしない（正常終了）
```

---

## confirm_reservation と Webhook の関係

「ユーザーが支払いボタンを押してから予約確定まで」の処理は 2 つのルートがあります。

```
[通常ルート] ユーザーブラウザ → confirm_reservation → 予約確定 → 確認メール

[フォールバック] Stripe → Webhook → 予約確定 → 確認メール
```

**なぜ Webhook が必要か：**  
ユーザーがカード決済後にブラウザを閉じた・ネットワークが切れた場合、`confirm_reservation` が実行されません。  
その場合でも Stripe から Webhook が届くため、予約を確定できます。

**どちらが先に実行されても問題ない設計：**

```
confirm_reservation が先 → 予約確定済み → Webhook は find_by_payment_intent で検出してスキップ
Webhook が先         → 予約確定済み → confirm_reservation は find_by_payment_intent で既存を返す
```

---

## テスト用カード番号

| カード番号 | 動作 |
|----------|------|
| `4242 4242 4242 4242` | 決済成功 |
| `4000 0000 0000 9995` | 決済失敗（カード不足） |
| `4000 0025 0000 3155` | 3D セキュア認証が必要 |

有効期限は未来の日付、CVC は任意の 3 桁、郵便番号は任意で OK です。

---

## エラーハンドリング

### Stripe API のエラー

`KKPAY_Stripe_Client::request()` は HTTP 2xx 以外のレスポンスを `WP_Error` に変換します。

```php
// 通常のキャンセル処理では Stripe /v1/refunds を呼び出さない。
// charge.refunded Webhook は、外部返金が発生した場合の同期用。
$result = KKPAY_Stripe_Client::request( 'GET', '/v1/payment_intents/' . rawurlencode( $pi_id ) );
if ( is_wp_error( $result ) ) {
    // $result->get_error_message() に Stripe のエラーメッセージが入っている
    return $result; // 呼び出し元に伝播させる
}
```

### よくある Stripe エラーコード

| Stripe エラー | 原因 | 対処 |
|-------------|------|------|
| `card_declined` | カード拒否 | ユーザーに別のカードを試すよう案内 |
| `insufficient_funds` | 残高不足 | 同上 |
| `authentication_required` | 3D セキュア要求 | Stripe.js が自動処理（通常は発生しない） |
| `charge_already_refunded` | 既に返金済み | 外部返金操作の重複 |

---

## 本番公開前のチェックリスト

- [ ] Stripe ダッシュボードで Webhook エンドポイントを登録した
- [ ] Production keys (`pk_live_`, `sk_live_`) are set in environment variables
- [ ] `KKPAY_STRIPE_WEBHOOK_SECRET` contains the Webhook signing secret (`whsec_`)
- [ ] テストモードで予約→決済→確認メールの動作を確認した
- [ ] テストモードでキャンセルしても返金されないことを確認した
- [ ] Stripe ダッシュボードで Webhook のテスト送信が成功することを確認した
