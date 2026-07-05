# 当日予約デポジット制 本番切替手順

## 目的

当日予約を無料即時確定フローから、1名あたり USD 13 のデポジット決済フローへ切り替える。

この手順では、既存の営業を止めずに限定 URL で本番同等確認を行い、問題発生時は旧コードを復元せずに `KKPAY_SAME_DAY_DEPOSIT_AMOUNT` を `0` に変更して無料即時確定へ戻す。

## 切替の前提

- 本番コードに `KKPAY_SAME_DAY_DEPOSIT_AMOUNT = 13` と `KKPAY_SAME_DAY_DEPOSIT_CURRENCY = 'usd'` が反映済みである。
- 本番 Stripe の公開キー、秘密キー、Webhook 署名シークレットが `.env` に設定済みである。
- Stripe Webhook の送信先が `https://{domain}/wp-json/kkpay/v1/webhook` で、`payment_intent.succeeded` を受け取れる。
- `kkpay_holds.seating_preference` のマイグレーションが本番に適用済み、または切替前の低アクセス時間帯に適用する。
- 当日予約フォームページに `[kkpay_same_day_reservation_form]`、確認・キャンセルページに `[kkpay_same_day_confirmation]` を配置できる。
- 旧無料フローの `kkpay_same_day_create` は復元しない。ロールバックは定数変更で行う。

## 旧無料フローと新デポジットフローの比較

| 観点 | 旧無料フロー | 新デポジットフロー | 判定 |
| --- | --- | --- | --- |
| 予約作成 | 入力後すぐ `kkpay_reservations` に作成 | ホールド作成、Stripe 決済、確定の順で作成 | 意図した差分 |
| AJAX action | `kkpay_same_day_create` | `kkpay_same_day_create_hold`、`kkpay_same_day_create_payment_intent`、`kkpay_same_day_confirm` | 意図した差分 |
| 席の仮押さえ | なし | `kkpay_holds` で 5 分間保持 | 意図した差分 |
| Stripe | 呼ばない | `metadata.type = same_day_deposit` の PaymentIntent を作成 | 意図した差分 |
| 金額 | `amount = 0` | `KKPAY_SAME_DAY_DEPOSIT_AMOUNT * number_of_people` | 意図した差分 |
| 決済ステータス | `not_required` | 成功時 `paid`、0ドルロールバック時 `not_required` | 意図した差分 |
| 席種 | `Table` / `Bar` | `Table` / `Bar` | 同じ |
| 残席計算 | 予約済み人数を反映 | 予約済み人数と有効ホールドを反映 | 意図した差分 |
| 確認ページ | メール検索 | メール検索、金額と決済状態も確認可能 | 意図した差分 |
| キャンセル | 返金なし | 返金なし、`kkpay_cancellations` とイベントに記録 | 意図した差分 |
| 管理画面 | 当日予約一覧 | 当日予約一覧にデポジット金額と決済状態を表示 | 意図した差分 |

## 本番切替前の限定 URL 確認

一般公開ページを差し替える前に、管理者だけが知る限定 URL の固定ページを2つ作る。

1. 当日予約テスト用ページを作成し、本文に `[kkpay_same_day_reservation_form]` を置く。
2. 確認・キャンセルテスト用ページを作成し、本文に `[kkpay_same_day_confirmation]` を置く。
3. ページの公開範囲は、運用担当者だけが URL を知っている状態にする。検索導線、メニュー、既存案内ページからはリンクしない。
4. 本番 Stripe キーで確認する場合は、実カードまたは Stripe が許可する本番確認手段を使い、確認後に必ずキャンセルまで実施する。
5. 確認対象日の席数設定で `Bar` と `Table` に十分な空きがあることを管理画面で確認する。
6. 低アクセス時間帯にプラグインを有効化または更新し、`kkpay_holds.seating_preference` が作成されていることを確認する。

確認 SQL:

```sql
DESCRIBE wp_kkpay_holds;

SELECT id, reservation_date, time_slot, seating_preference, number_of_people, expires_at
FROM wp_kkpay_holds
ORDER BY id DESC
LIMIT 20;
```

`wp_` は本番 DB の `$wpdb->prefix` に置き換える。

## 限定 URL での決済フロー確認

1. 当日予約テスト用ページを開く。
2. 言語、名前、メール、人数、席種、時間枠を入力する。
3. デポジット金額が `人数 * KKPAY_SAME_DAY_DEPOSIT_AMOUNT` で表示されることを確認する。
4. カード情報を入力して予約を確定する。
5. 完了画面に予約内容、デポジット金額、返金なしの文言が表示されることを確認する。
6. 管理画面の当日予約一覧で、対象予約の `amount` と `payment_status = paid` を確認する。
7. Stripe ダッシュボードで PaymentIntent の metadata に `type = same_day_deposit`、`hold_token`、`reservation_date`、`time_slot`、`seating_preference` が入っていることを確認する。
8. 確認・キャンセルテスト用ページで同じメールアドレスを検索し、予約が表示されることを確認する。
9. キャンセルを実行し、返金なしの文言が表示されることを確認する。
10. 管理画面で `status = cancelled`、`cancelled_at`、決済状態表示を確認する。

確認 SQL:

```sql
SELECT id, reservation_date, time_slot, seating_preference, number_of_people,
       status, payment_status, amount, currency, stripe_payment_intent_id,
       stripe_charge_id, hold_id, created_at, cancelled_at
FROM wp_kkpay_reservations
WHERE reservation_type = 'same_day'
ORDER BY id DESC
LIMIT 20;

SELECT reservation_id, refund_status, refund_amount, stripe_refund_id, created_at
FROM wp_kkpay_cancellations
ORDER BY id DESC
LIMIT 20;

SELECT reservation_id, event_type, actor_type, created_at
FROM wp_kkpay_reservation_events
WHERE event_type IN ('reservation_created', 'reservation_cancelled')
ORDER BY id DESC
LIMIT 20;
```

## 0ドルロールバック手順の事前確認

切替前に、ステージングまたは短時間の本番確認で一度だけ実行する。

1. `early-reservation-system.php` の `KKPAY_SAME_DAY_DEPOSIT_AMOUNT` を `0` に変更する。
2. 当日予約テスト用ページを開く。
3. カード入力欄が表示されないことを確認する。
4. 予約を送信し、Stripe を介さずに即時確定することを確認する。
5. 管理画面または SQL で `payment_status = not_required`、`amount = 0`、`stripe_payment_intent_id IS NULL` を確認する。
6. 確認後、`KKPAY_SAME_DAY_DEPOSIT_AMOUNT` を `13` に戻す。
7. 再度ページを開き、カード入力欄とデポジット金額表示が戻ることを確認する。

この確認で旧 `kkpay_same_day_create` を復元してはいけない。削除済みコードの復元は正式なロールバック手段ではない。

## 本番切替手順

推奨タイミングは、営業前または当日予約のアクセスが少ない時間帯。

1. 本番 DB とファイルのバックアップを取得する。
2. Stripe Webhook の受信状態を確認する。
3. `KKPAY_SAME_DAY_DEPOSIT_AMOUNT = 13` であることを確認する。
4. `kkpay_holds.seating_preference` が存在することを確認する。
5. 当日と翌営業日の `Bar` / `Table` の席数設定を確認する。
6. 既存の公開当日予約ページ本文を控える。
7. 既存の公開確認・キャンセルページ本文を控える。
8. 公開当日予約ページの本文を `[kkpay_same_day_reservation_form]` に差し替える。
9. 公開確認・キャンセルページの本文を `[kkpay_same_day_confirmation]` に差し替える。
10. 公開ページを開き、フォーム、カード入力欄、確認ページが表示されることを確認する。
11. 旧無料フローの受付導線が公開ページから消えていることを確認する。
12. 旧プラグインや旧ページは、初日の監視が終わるまで削除しない。

## 問題発生時のロールバック

### 決済だけを止めて無料即時確定へ戻す

このロールバックを第一選択とする。

1. `early-reservation-system.php` の `KKPAY_SAME_DAY_DEPOSIT_AMOUNT` を `0` に変更する。
2. 必要に応じて `KKPAY_VERSION` を上げ、ブラウザと WordPress のキャッシュを更新する。
3. 公開当日予約ページでカード入力欄が表示されないことを確認する。
4. テスト予約を作成し、`payment_status = not_required`、`amount = 0`、`stripe_payment_intent_id IS NULL` で即時確定することを確認する。
5. 既にデポジット決済済みの予約はそのまま維持する。以後の新規予約だけが無料即時確定になる。
6. 管理画面では、同じ日に `paid` の予約と `not_required` の予約が混在する可能性があることを運用担当者へ共有する。

### 受付自体を一時停止する

Stripe 障害、Webhook 障害、DB 障害など、0ドルロールバックでも収まらない場合だけ行う。

1. 公開当日予約ページの本文を、受付停止案内に差し替える。
2. 確認・キャンセルページは残す。既存予約の確認とキャンセルを止めない。
3. 管理画面で当日予約一覧を監視し、直前に作成された予約の状態を確認する。
4. 原因調査が終わるまで、旧無料フローのコード復元や旧 `kkpay_same_day_create` の再追加は行わない。

## 切替後初日の監視

切替後の初日は、最低でも次を確認する。

- 新規予約が1件以上 `payment_status = paid` で作成される。
- Stripe ダッシュボード上の PaymentIntent 金額が予約人数と一致する。
- 予約確認ページで対象予約を検索できる。
- キャンセルが1件以上 `kkpay_cancellations` と `kkpay_reservation_events` に記録される。
- キャンセル後も Stripe refund は作成されない。
- 管理画面の時間枠別合計人数、`Bar` / `Table` 別人数、キャンセル済み表示切替が崩れていない。
- お客様からカード入力、予約確認、キャンセル、メール不達に関する問い合わせが増えていない。

## PR7 テストチェックリスト

- [ ] PR7-T1: テスト環境で、ホールド、決済、確定、キャンセルが正常に完了する。
- [ ] PR7-T2: 限定 URL で、本番同等設定と Stripe 本番キーを使った実地確認ができる。
- [ ] PR7-T3: `KKPAY_SAME_DAY_DEPOSIT_AMOUNT` を `0` に変更し、Stripe を介さない即時確定へ戻せることを確認する。
- [ ] PR7-T4: 旧無料フローと新デポジットフローの比較チェックリストが、意図した差分だけになっている。
- [ ] PR7-T5: 本番切替後の初日に、実予約、実決済、キャンセルが最低1件ずつ想定通り動くことを運用担当者が確認する。

