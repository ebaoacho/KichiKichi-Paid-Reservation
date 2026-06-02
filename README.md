# KichiKichi Paid Reservation

KichiKichi Paid Reservation は、WordPress 上で動作するキチキチ向け予約・決済プラグインです。

現在はプレミアム予約とスペシャルプレミアム予約を扱い、Stripe 決済、仮押さえ、予約確定、キャンセル、管理画面での席数設定を提供します。今後の実装では、既存の当日予約システムもこのプラグインの共通予約基盤へ統合し、当日予約・プレミアム予約・スペシャルプレミアム予約が同じ空席計算と同じ予約テーブルを使う構成にします。

## 目的

このリポジトリの最終的な目的は、予約種別ごとに分かれていた空席判定や保存先を統合し、異なる予約種別同士のバッティングを防ぐことです。

統合後は、すべての予約を `kkpay_reservations` に保存します。

| 予約種別 | `reservation_type` | 席種 |
| --- | --- | --- |
| 当日予約 | `same_day` | `Table` または `Bar` |
| プレミアム予約 | `premium` | `Bar` |
| スペシャルプレミアム予約 | `special_premium` | `Bar` |

当日予約は `Table` / `Bar` を選択できます。プレミアム予約とスペシャルプレミアム予約は、既存仕様との互換性を保つため `Bar` 固定として扱います。

## アーキテクチャ

コードは以下の層に分けています。

| 層 | 役割 |
| --- | --- |
| Controllers | WordPress AJAX / REST / shortcode の入口。リクエストを受け、レスポンスを返す |
| Validators | 入力値の検証と正規化 |
| Services | 予約可否、残席計算、トランザクション、決済後処理などの業務ロジック |
| Repositories | `$wpdb` を使った DB 読み書き |
| Infrastructure | Stripe やメール設定など外部連携 |

依存方向は上から下です。Repository は Service を呼ばず、Service は Controller を呼びません。

```text
Controller
  -> Validator
  -> Service
       -> Repository
       -> Infrastructure
```

## 主要テーブル

| テーブル | 役割 |
| --- | --- |
| `kkpay_holds` | 決済前の仮押さえ |
| `kkpay_reservations` | 確定予約の正本。今後は全予約タイプをここに集約 |
| `kkpay_cancellations` | 既存プレミアム予約のキャンセル履歴 |
| `kkpay_accepted_dates` | 既存互換用の受付日・席数設定。段階的に `kkpay_slot_capacities` へ移行 |
| `kkpay_premium_reservations` | スペシャルプレミアム予約の決済リンク・キャンセルリンク管理 |
| `kkpay_slot_capacities` | 日付・時間帯・席種別の定員設定 |
| `kkpay_reservation_events` | 予約操作の監査ログ |

## 共通予約基盤の最終像

最終的には、予約作成・日時確定・日時変更・キャンセルが以下の考え方で統一されます。

1. 管理者が `kkpay_slot_capacities` に日付・時間帯・席種ごとの定員を設定する。
2. 予約作成時は対象の `capacity_date + time_slot + seating_preference` 行を `SELECT ... FOR UPDATE` でロックする。
3. ロック後に、同じ日付・時間帯・席種の `active` 予約人数を再計算する。
4. 決済前の仮押さえがある場合は hold 人数も差し引く。
5. `capacity - active reservations - active holds` が申込人数以上なら予約を作成または更新する。
6. 満席、無効枠、重複、決済失敗などの場合は `WP_Error` を返し、トランザクションを `ROLLBACK` する。
7. 成功時は `kkpay_reservation_events` に操作履歴を残す。

残席計算の基本式は以下です。

```text
remaining =
  kkpay_slot_capacities.capacity
  - SUM(active kkpay_reservations.number_of_people)
  - SUM(active kkpay_holds.number_of_people)
```

対象予約は以下で絞ります。

```sql
WHERE reservation_date = ?
  AND time_slot = ?
  AND seating_preference = ?
  AND status = 'active'
  AND cancelled_at IS NULL
```

## バッティング防止の設計

このシステムで防ぎたいバッティングは、同じ日時・同じ席種に対して複数の入口から予約が入り、管理者が設定した定員を超えてしまうことです。

対象になる入口は以下です。

- 当日予約
- プレミアム予約の日時確定
- プレミアム予約の日時変更
- スペシャルプレミアム予約の日時確定
- スペシャルプレミアム予約の日時変更

バッティング防止は複数の対策を組み合わせます。

### 1. 席数設定行をロック対象にする

ロック対象は予約行ではなく、`kkpay_slot_capacities` の行です。

```sql
SELECT *
FROM kkpay_slot_capacities
WHERE capacity_date = ?
  AND time_slot = ?
  AND seating_preference = ?
LIMIT 1
FOR UPDATE
```

予約がまだ0件の枠でも、席数設定行は存在します。そのため、予約行が存在しない状態でも同じ枠への同時予約を直列化できます。

### 2. ロック後に残席を再計算する

画面表示時の残席は参考値です。実際の予約可否は、トランザクション内でロックを取った後に再計算します。

これにより、以下のような競合に備えます。

```text
Aさんが残席1を見る
Bさんも残席1を見る
Aさんが予約確定
Bさんも予約確定しようとする
```

ロック後に再計算することで、Bさんの処理は Aさんの予約確定後の人数を見て満席判定できます。

### 3. キャンセル済み予約を残席計算から除外する

キャンセルは物理削除ではなく、`status = cancelled` と `cancelled_at` を更新します。

残席計算では以下のみを有効予約として数えます。

```text
status = active
cancelled_at IS NULL
```

これにより、キャンセル後は席が残席計算へ戻ります。

### 4. 重複予約をDB制約とServiceで防ぐ

既存の `kkpay_reservations` には以下の UNIQUE 制約があります。

```sql
UNIQUE KEY email_date_slot (email, reservation_date, time_slot)
```

同じメールアドレス・同じ日付・同じ時間帯で複数予約を作れないようにしています。

Service 層でも、予約作成や日時変更前に以下を確認します。

- 同じ `email + reservation_date + time_slot` の予約がないか
- 更新時は自分自身の予約IDを除外して重複を確認する
- Stripe PaymentIntent の再送・二重処理では既存予約を再利用する

### 5. Stripe の冪等性に備える

Stripe 決済は、ブラウザの確定処理と Webhook の両方から同じ PaymentIntent を処理する可能性があります。

そのため、`stripe_payment_intent_id` で既存予約を検索し、同じ PaymentIntent に対して予約を二重作成しないようにします。

注意点として、PaymentIntent ID はクレジットカードごとに一意ではありません。別セッションで別 PaymentIntent が作られた場合は、PaymentIntent ID だけでは二重支払いを防げません。そのため、メール・日時の UNIQUE 制約、hold、残席ロック、決済後の重複処理を組み合わせて守ります。

## 想定しているエラーと対策

| エラー/リスク | 対策 |
| --- | --- |
| 同時予約で定員超過する | `kkpay_slot_capacities` 行を `FOR UPDATE` でロックし、ロック後に残席再計算 |
| 予約0件の枠では予約行をロックできない | 予約行ではなく席数設定行をロック対象にする |
| キャンセル済み予約が残席を消費し続ける | `status = active` かつ `cancelled_at IS NULL` のみ残席計算に含める |
| 同じメール・同じ日時の二重予約 | `email_date_slot` UNIQUE 制約と Service の重複チェック |
| ブラウザ確定処理と Stripe Webhook の二重実行 | `stripe_payment_intent_id` で既存予約を検索し、同じ PaymentIntent を再利用 |
| 決済前に席だけ押さえたまま放置される | `kkpay_holds` に期限を持たせ、WP-Cron で期限切れ hold を掃除 |
| `dbDelta()` が既存カラムの default/nullability/size を更新しない | 必要なカラムは明示的な `ALTER TABLE` で補正 |
| MySQL / MariaDB の JSON 型差異 | `kkpay_reservation_events.event_payload` は `LONGTEXT` に JSON 文字列として保存 |
| 個人情報を監査ログへ過剰保存する | メール検索用は `email_hash`、IP/User-Agent はハッシュ化し、イベント payload は必要最小限にする |
| DB更新失敗 | Repository は失敗時に `WP_Error` を返し、Service は `ROLLBACK` する |
| WP-Cron 遅延で期限切れ hold が残る | hold は期限付きで判定し、Cron は掃除用とする。高精度運用ではサーバー cron で `wp-cron.php` を叩く |

## 現在の段階

このブランチでは、当日予約統合の Step 1 として以下を追加しています。

- `kkpay_reservations` への共通予約カラム追加
- `kkpay_slot_capacities` の追加
- `kkpay_reservation_events` の追加
- 既存予約データのバックフィル
- `kkpay_accepted_dates` から `kkpay_slot_capacities` への初期移行
- 新規 Repository の追加
- 予約作成時の共通カラム補完
- キャンセル時の `status = cancelled` 更新
- Step 1 確認スクリプト
- `kkpay_slot_capacities` をロック対象にした共通空席ロックサービス
- Step 2 確認スクリプト
- プレミアム予約・スペシャルプレミアム予約の `Bar` 固定化
- プレミアム予約・スペシャルプレミアム予約の日時確定・日時変更を共通空席ロックサービスへ接続
- 予約作成・日時変更・キャンセル時の `kkpay_reservation_events` への監査ログ記録
- Step 3 確認スクリプト
- 当日予約 API の Service / Validator / Controller 追加
- 当日予約の受付開始・停止、空き枠取得、作成、確認、キャンセル API 追加
- 当日予約作成・キャンセル時の `kkpay_reservation_events` への監査ログ記録
- Step 4 確認スクリプト
- `[kkpay_same_day_reservation_form]` ショートコード追加
- 既存当日予約フォームに近い入力順のテンプレート追加
- 人数・席種別に応じて Step 4 API から空き時間枠を取得するフロント JS 追加
- Step 5 確認スクリプト

Step 5 のフォーム固有文言（入力必須、メール不一致、スロット未選択など）は `assets/js/kkpay-same-day.js` の `LABELS` で管理します。サーバー由来のエラーメッセージは `KKPAY_MESSAGES` を優先し、JS 固有文言だけ `LABELS` にフォールバックします。

Step 4 の当日予約作成では、同じメール・同じ日付に既存の active 行がある場合は `FOR UPDATE` でロックします。active 行がまだ存在しない場合、同じメール・同じ日付・別スロットへの完全な同時二重作成は行ロックだけでは防げないため、実運用上は低頻度の制約として扱い、同一スロットの最終防御は `email_date_slot` UNIQUE KEY に委ねます。

また、`doc/01_directory_structure.md` は Step 4 で追加したファイルだけでなく、Step 1〜3 で実態と乖離していた既存の追加ファイルも合わせて反映しています。

次の Step では、当日予約の確認・キャンセルページを追加し、既存の確認導線を新しい予約台帳へ接続します。

## 主要フロー

### プレミアム予約

1. ユーザーが日付・時間帯・人数・名前・メール・言語を入力する。
2. サーバーが仮押さえ `kkpay_holds` を作成する。
3. Stripe PaymentIntent を作成する。
4. Stripe.js でカード決済する。
5. 決済成功後、`kkpay_reservations` に `reservation_type = premium` として保存する。
6. 確認メールを送信する。

### スペシャルプレミアム予約

1. 管理者が決済リンクを発行する。
2. ユーザーがリンクから支払う。
3. 日時確定時に `kkpay_reservations` に `reservation_type = special_premium` として保存する。
4. 席種は `Bar` 固定として扱う。

### 当日予約の最終像

1. ユーザーが当日予約フォームを開く。
2. 受付時間・営業日・席種・人数に応じて予約可能枠を表示する。
3. ユーザーが `Table` または `Bar` を選ぶ。
4. サーバーが共通空席ロックサービスを通して予約可否を再判定する。
5. 成功時は `kkpay_reservations` に `reservation_type = same_day` として保存する。

## セットアップ

### 必要環境

| 項目 | 内容 |
| --- | --- |
| PHP | 7.2 以上 |
| WordPress | 5.6 以上 |
| DB | MySQL / MariaDB |
| 決済 | Stripe |
| メール | `wp_mail()`。本番では WP Mail SMTP 推奨 |

### 環境変数

`.env.template` を `.env` にコピーし、以下を設定します。

| 変数 | 内容 |
| --- | --- |
| `KKPAY_STRIPE_PUBLISHABLE_KEY` | Stripe publishable key |
| `KKPAY_STRIPE_SECRET_KEY` | Stripe secret key |
| `KKPAY_STRIPE_WEBHOOK_SECRET` | Stripe Webhook signing secret |
| `KKPAY_FROM_EMAIL` | 送信元メールアドレス |
| `KKPAY_FROM_NAME` | 送信元名 |

`wp-config.php` 定数やサーバー環境変数でも同名の値を指定できます。

### Stripe Webhook

Stripe Dashboard で Webhook endpoint を追加します。

```text
https://example.com/wp-json/kkpay/v1/webhook
```

Listen to events:

- `payment_intent.succeeded`
- `charge.refunded`

Webhook signing secret を `KKPAY_STRIPE_WEBHOOK_SECRET` に設定します。

## Shortcodes

| Shortcode | 用途 |
| --- | --- |
| `[kkpay_reservation_form]` | プレミアム予約フォーム |
| `[kkpay_payment_page]` | Stripe 決済ページ |
| `[kkpay_my_reservation]` | 予約確認・キャンセル |
| `[kkpay_same_day_reservation_form]` | 当日予約フォーム |

今後の当日予約統合では、以下の shortcode を追加する予定です。

| Shortcode | 用途 |
| --- | --- |
| `[kkpay_same_day_confirmation]` | 当日予約確認・キャンセル |

## 確認スクリプト

Step 1 のスキーマ確認用に、読み取り専用スクリプトを用意しています。
Step 2 / Step 3 / Step 4 の確認は Step 1 のDBマイグレーションが適用済みであることを前提にしています。Step 5 の確認スクリプトはファイルと登録内容の静的確認のみで、DB接続は不要です。

```powershell
C:\xampp\php\php.exe tools\kkpay-step1-check.php C:\xampp\htdocs\kichikichi\wp-load.php
C:\xampp\php\php.exe tools\kkpay-step2-check.php C:\xampp\htdocs\kichikichi\wp-load.php
C:\xampp\php\php.exe tools\kkpay-step3-check.php C:\xampp\htdocs\kichikichi\wp-load.php
C:\xampp\php\php.exe tools\kkpay-step4-check.php C:\xampp\htdocs\kichikichi\wp-load.php
C:\xampp\php\php.exe tools\kkpay-step5-check.php
```

期待結果:

```text
Result: PASSED
```

このスクリプトは以下を確認します。

- 必要な7テーブルが存在すること
- `kkpay_reservations` に追加カラムが存在すること
- `email_date_slot` UNIQUE KEY が維持されていること
- `kkpay_slot_capacities` のカラムと UNIQUE KEY が正しいこと
- `kkpay_reservation_events.event_payload` が `longtext` であること
- 既存データのバックフィル漏れがないこと
- 新規 Repository がロードされていること

PHP構文チェックの例:

```powershell
C:\xampp\php\php.exe -l includes\class-kkpay-activator.php
C:\xampp\php\php.exe -l includes\Repositories\class-kkpay-reservation-repository.php
C:\xampp\php\php.exe -l includes\Repositories\class-kkpay-slot-capacity-repository.php
C:\xampp\php\php.exe -l includes\Repositories\class-kkpay-reservation-event-repository.php
C:\xampp\php\php.exe -l includes\Services\class-kkpay-capacity-service.php
C:\xampp\php\php.exe -l includes\Services\class-kkpay-hold-service.php
C:\xampp\php\php.exe -l includes\Services\class-kkpay-reservation-service.php
C:\xampp\php\php.exe -l includes\Services\class-kkpay-cancellation-service.php
C:\xampp\php\php.exe -l includes\Services\class-kkpay-premium-reservation-service.php
C:\xampp\php\php.exe -l includes\Services\class-kkpay-same-day-reservation-service.php
C:\xampp\php\php.exe -l includes\Validators\class-kkpay-same-day-reservation-validator.php
C:\xampp\php\php.exe -l includes\Controllers\class-kkpay-same-day-reservation-controller.php
C:\xampp\php\php.exe -l templates\same-day-reservation-form.php
C:\xampp\php\php.exe -l tools\kkpay-step1-check.php
C:\xampp\php\php.exe -l tools\kkpay-step2-check.php
C:\xampp\php\php.exe -l tools\kkpay-step3-check.php
C:\xampp\php\php.exe -l tools\kkpay-step4-check.php
C:\xampp\php\php.exe -l tools\kkpay-step5-check.php
```

## 運用上の注意

- プラグイン無効化時は WP-Cron の `kkpay_cleanup_holds` を解除します。予約データやテーブルは削除しません。
- `kkpay_accepted_dates` は互換性のため当面残します。新しい席数管理の正本は `kkpay_slot_capacities` へ移行します。
- 当日予約統合後も既存の予約導線を一気に置き換えず、段階的に移行します。
- キャンセルは物理削除ではなく状態更新で扱います。監査・問い合わせ対応・残席回復のためです。
- `event_payload` には個人情報を過剰に入れず、操作内容を後から追える最小限の状態差分を入れます。

## 関連ドキュメント

| ファイル | 内容 |
| --- | --- |
| `doc/00_overview.md` | 全体アーキテクチャ |
| `doc/01_directory_structure.md` | ディレクトリ構成 |
| `doc/02_layer_controllers.md` | Controller 層 |
| `doc/03_layer_validators.md` | Validator 層 |
| `doc/04_layer_services.md` | Service 層 |
| `doc/05_layer_repositories.md` | Repository 層 |
| `doc/06_layer_infrastructure.md` | Infrastructure 層 |
| `doc/14_same_day_reservation_integration_design.md` | 当日予約統合設計 |
| `doc/15_same_day_reservation_current_spec.md` | 既存当日予約仕様 |
