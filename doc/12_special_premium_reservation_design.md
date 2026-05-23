# スペシャルプレミアム予約 設計書

## 目的

既存の通常予約とは別導線で、マスターが実際に話したお客様だけに専用決済リンクを渡し、先に1席あたり USD 32 の入金を受け付ける。
入金後、マスターが管理画面で予約日と時間枠を確定し、確定した予約は通常予約と同じ空席数計算に反映する。

## 決定事項

- 金額は1席あたり USD 32。2席なら USD 64、3席なら USD 96 とする。
- 決済リンクは管理画面から顧客ごとのトークン付きURLとして発行する。
- 決済リンクの有効期限は発行から24時間。
- 有効期限を過ぎた未入金リンクは自動処理で `expired` に更新する。
- 決済画面の入力項目は、言語、名前、メールアドレス、席数のみ。
- 席数は1〜8席まで選択できる。
- 名前は通常予約と同じくアルファベット、スペース、ピリオド、アポストロフィ、ハイフンのみ許可する。
- 入金完了時、お客様へ自動メールを送信し、マスターにもCCで送信する。
- マスターへのCC先は既存の `KKPAY_FROM_EMAIL` を使用する。
- 入金後、マスターが管理画面で日付と時間枠を入力する。
- お客様の予約日時確定・変更の日付は、当日から1か月後までの範囲で自由入力し、時間枠は既存の `slot_1` から `slot_6` を使う。
- 席数設定タブで表示・設定できる日付範囲は、当日から2か月後の月末までとする。
- プレミアム予約の通常予約への空席反映は、お客様が選んだ席数で行う。
- 日時確定後、お客様へ日時確定メールを送信する。
- 日時確定メールにはキャンセル案内を含める。
- 日時確定後も管理画面から日時変更できる。変更時も当日から1か月後までを選択可能範囲とし、空席チェックを行う。
- 日時変更後、お客様へ日時変更メールを自動送信する。
- キャンセルリンクは日時確定後のみ管理画面から発行できる。
- キャンセルリンクは一度使ったら無効化する。有効期限は設けない。
- キャンセル後は管理画面のステータスを `cancelled` とし、履歴として残す。
- 管理画面は既存プラグインの新しいタブとして追加する。
- お客様画面は通常予約とは別ショートコードで提供する。
  - 決済ページ: `[kkpay_premium_payment]`
  - キャンセルページ: `[kkpay_premium_cancel]`
- 既存ファイルの文字化け修正は実装範囲に含める。

## キャンセル・返金ポリシー

スペシャルプレミアム予約は、予約日の3日前までは全額返金する。
予約日の3日前を過ぎた場合は返金しない。

判定基準:

- 予約日時が確定済みの場合、`reservation_date` を基準にする。
- キャンセル実行日が `reservation_date` の3日前以前なら返金対象。
- それ以降は返金なし。
- キャンセルリンクは日時確定後のみ発行できるため、日時未確定のキャンセルは管理画面で個別対応する。

注記:

- 「手数料・権利金として返金なし」という前提よりも、「3日前まで全額返金」をスペシャルプレミアム予約の正式なキャンセルポリシーとして優先する。
- 返金対象の場合のみ Stripe Refund API を呼び出す。
- 返金なしの場合は Stripe Refund API を呼び出さない。

## 全体フロー

### 1. 決済リンク発行

1. マスターが管理画面の「スペシャルプレミアム予約」タブを開く。
2. 「決済リンク発行」ボタンを押す。
3. システムが専用トークンを生成し、発行時刻と有効期限を保存する。
4. 管理画面に決済URLを表示する。
5. マスターがお客様へURLを送る。

### 2. お客様の入金

1. お客様がトークン付きURLで `[kkpay_premium_payment]` のページへアクセスする。
2. システムがトークンの存在、有効期限、未使用状態を検証する。
3. お客様が言語、名前、メールアドレスを入力する。
4. Stripe PaymentIntent を作成する。
5. お客様が USD `32 * 席数` を決済する。
6. 決済成功後、プレミアム予約レコードを `paid` に更新する。
7. お客様へ入金完了メールを送信し、マスターをCCに入れる。

### 3. 日時確定

1. マスターが管理画面で入金済みレコードを確認する。
2. 日付と時間枠を入力する。
3. システムが通常予約テーブル `kkpay_reservations` に予約レコードを作成する。
4. プレミアム予約レコードに通常予約IDを紐づける。
5. プレミアム予約レコードのステータスを `scheduled` に更新する。
6. お客様へ日時確定メールを送信する。

### 4. キャンセルリンク発行

1. マスターが日時確定済みのプレミアム予約に対してキャンセルリンクを発行する。
2. システムがキャンセルトークンを生成する。
3. 管理画面にキャンセルURLを表示する。
4. マスターがお客様へURLを送る。

### 5. お客様キャンセル

1. お客様が `[kkpay_premium_cancel]` のページへアクセスする。
2. システムがキャンセルトークンの存在、未使用状態、対象予約がキャンセル済みでないことを検証する。
3. お客様がキャンセルボタンを押す。
4. システムが予約日の3日前までかを判定する。
5. 返金対象なら Stripe Refund API で支払い済み全額を返金する。
6. 通常予約レコードの `cancelled_at` を更新する。
7. プレミアム予約レコードのステータスを `cancelled` に更新する。
8. キャンセルトークンを使用済みにする。
9. お客様へキャンセル完了メールを送信し、マスターをCCに入れる。

## データ設計

### 方針

入金時点では予約日が未確定のため、既存の `kkpay_reservations` に直接保存しない。
既存テーブルは `reservation_date` と `time_slot` が必須であり、通常予約の一意制約や空席計算にも使われているため、未確定データを入れると既存ロジックを壊しやすい。

そのため、入金から日時確定前までは新テーブル `kkpay_premium_reservations` で管理する。
日時確定時に `kkpay_reservations` へ通常予約レコードを作成し、その時点から通常予約と同じ空席数計算に反映する。

### 新テーブル: `kkpay_premium_reservations`

| カラム | 型 | 内容 |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | 主キー |
| `payment_token` | VARCHAR(64) | 決済リンク用トークン |
| `payment_token_expires_at` | DATETIME | 決済リンク有効期限 |
| `payment_token_used_at` | DATETIME NULL | 決済リンク使用日時 |
| `cancel_token` | VARCHAR(64) NULL | キャンセルリンク用トークン |
| `cancel_token_used_at` | DATETIME NULL | キャンセルリンク使用日時 |
| `reservation_id` | BIGINT UNSIGNED NULL | `kkpay_reservations.id` |
| `reservation_date` | DATE NULL | 確定後の日付 |
| `time_slot` | VARCHAR(20) NULL | 確定後の時間枠 |
| `language` | VARCHAR(10) | お客様の言語 |
| `name` | VARCHAR(100) NULL | お客様名 |
| `email` | VARCHAR(100) NULL | メールアドレス |
| `stripe_payment_intent_id` | VARCHAR(100) NULL | Stripe PaymentIntent ID |
| `stripe_charge_id` | VARCHAR(100) NULL | Stripe Charge ID |
| `stripe_refund_id` | VARCHAR(100) NULL | Stripe Refund ID |
| `payment_status` | VARCHAR(20) | `unpaid`, `paid`, `refunded` |
| `status` | VARCHAR(30) | 業務ステータス |
| `amount` | INT UNSIGNED | `32 * number_of_people` |
| `number_of_people` | TINYINT UNSIGNED | お客様が選択した席数 |
| `currency` | VARCHAR(3) | `usd` |
| `cancelled_at` | DATETIME NULL | キャンセル日時 |
| `created_at` | DATETIME | 作成日時 |
| `updated_at` | DATETIME | 更新日時 |

推奨インデックス:

- `UNIQUE KEY payment_token (payment_token)`
- `UNIQUE KEY cancel_token (cancel_token)`
- `UNIQUE KEY payment_intent (stripe_payment_intent_id)`
- `KEY status (status)`
- `KEY reservation_id (reservation_id)`
- `KEY reservation_date_slot (reservation_date, time_slot)`

### ステータス定義

`status` はプレミアム予約の業務状態を表す。

| status | 意味 |
| --- | --- |
| `link_issued` | 決済リンク発行済み、未入金 |
| `paid` | 入金済み、日時未確定 |
| `scheduled` | 日時確定済み |
| `cancel_link_issued` | キャンセルリンク発行済み |
| `cancelled` | キャンセル済み |
| `expired` | 決済リンク期限切れ |

`payment_status` は決済状態を表す。

| payment_status | 意味 |
| --- | --- |
| `unpaid` | 未決済 |
| `paid` | 決済済み |
| `refunded` | 返金済み |

## 既存 `kkpay_reservations` への反映

日時確定時に、以下の内容で既存予約テーブルへ作成する。

- `hold_id`: `NULL`
- `reservation_date`: 管理画面で入力した日付
- `time_slot`: 管理画面で選択した時間枠
- `name`: プレミアム予約のお客様名
- `email`: プレミアム予約のお客様メール
- `language`: プレミアム予約の言語
- `stripe_payment_intent_id`: プレミアム予約の PaymentIntent ID
- `stripe_charge_id`: プレミアム予約の Charge ID
- `payment_status`: `paid`
- `amount`: `32 * number_of_people`
- `number_of_people`: お客様が決済画面で選択した席数

注意:

- 空席数への反映は、お客様が決済画面で選択した席数を使う。
- 既存の `UNIQUE KEY (email, reservation_date, time_slot)` により、同じメール・同じ日付・同じ時間枠の重複予約は作成できない。

## 管理画面設計

既存 `KKPAY_Admin` のタブに `premium_reservations` を追加する。

タブ名:

- スペシャルプレミアム予約

表示項目:

- ID
- ステータス
- 名前
- メールアドレス
- 言語
- 入金状況
- 予約日
- 時間枠
- 作成日時
- 更新日時
- キャンセル日時

決済リンクとキャンセルリンクはテーブル列として常時表示せず、各発行操作の成功後に結果パネルへ表示する。

操作:

- 決済リンク発行
- 日時確定
- キャンセルリンク発行
- CSV出力

日時確定フォーム:

- 日付入力
- 選択可能範囲は JST 基準で当日から1か月後まで。
- 時間枠選択
  - `slot_1`
  - `slot_2`
  - `slot_3`
  - `slot_4`
  - `slot_5`
  - `slot_6`

バリデーション:

- 入金済みでない予約は日時確定できない。
- キャンセル済み予約は日時確定できない。
- 日付は空不可。
- 日付は JST 基準で当日以上、1か月後以下のみ許可する。
- 時間枠は既存 `KKPAY_SLOT_TYPES` に存在する値のみ。
- 既に日時確定済みの場合も変更可能とする。
- 日時変更時は、変更前の自分の席数を差し引いたうえで変更先スロットの空席チェックを行う。
- 日時変更後はお客様へ日時変更メールを送る。

## ショートコード設計

### `[kkpay_premium_payment]`

役割:

- トークン付き決済リンクから入ったお客様に入金フォームを表示する。

URL例:

```text
https://example.com/premium-payment/?token=xxxxxxxx
```

表示条件:

- `token` が存在する。
- `payment_token` と一致するレコードがある。
- `status` が `link_issued`。
- `payment_token_used_at` が `NULL`。
- `payment_token_expires_at` が現在時刻より未来。

フォーム:

- 言語
- 名前
- メールアドレス
- 席数
- Stripe Card Element
- USD `32 * 席数` の決済ボタン

### `[kkpay_premium_cancel]`

役割:

- キャンセルトークン付きURLから入ったお客様にキャンセルボタンを表示する。

URL例:

```text
https://example.com/premium-cancel/?token=xxxxxxxx
```

表示条件:

- `token` が存在する。
- `cancel_token` と一致するレコードがある。
- `status` が `cancel_link_issued` または `scheduled`。
- `cancel_token_used_at` が `NULL`。
- `cancelled_at` が `NULL`。
- `reservation_id` が存在する。

表示内容:

- 名前
- 予約日
- 時間枠
- キャンセルポリシー
- キャンセルボタン

## AJAX / REST 設計

### 公開AJAX

- `kkpay_premium_create_payment_intent`
  - 決済トークン、言語、名前、メールアドレスを受け取り、PaymentIntent を作成する。
- `kkpay_premium_confirm_payment`
  - Stripe 決済成功後、PaymentIntent を検証し、プレミアム予約を `paid` に更新する。
- `kkpay_premium_cancel_reservation`
  - キャンセルトークンを検証し、キャンセル処理と必要に応じた返金を行う。

### 管理画面AJAX

- `kkpay_premium_issue_payment_link`
  - 決済リンクを発行する。
- `kkpay_premium_schedule_reservation`
  - 入金済み予約に日付と時間枠を設定し、通常予約テーブルへ反映する。
- `kkpay_premium_issue_cancel_link`
  - 日時確定済み予約にキャンセルリンクを発行する。
- `kkpay_premium_export_csv`
  - CSVを出力する。

### Webhook

既存の `/wp-json/kkpay/v1/webhook` を拡張する。

`payment_intent.succeeded`:

- metadata に `type=premium_reservation` がある場合、プレミアム予約として処理する。
- 通常予約の PaymentIntent と処理を分岐する。

`charge.refunded`:

- PaymentIntent ID からプレミアム予約を探す。
- 見つかった場合は `payment_status=refunded` に更新する。
- 通常予約側の既存処理にも影響しないよう分岐する。

## Stripe metadata

PaymentIntent 作成時に以下を設定する。

| key | value |
| --- | --- |
| `type` | `premium_reservation` |
| `premium_id` | `kkpay_premium_reservations.id` |
| `payment_token` | 決済トークン |
| `amount` | `32` |
| `currency` | `usd` |
| `email` | お客様メール |

## メール設計

### 入金完了メール

宛先:

- To: お客様
- CC: マスター

内容:

- 入金を受け付けたこと
- 金額 USD `32 * number_of_people`
- 予約日時は後日マスターから確定されること
- キャンセルは日時確定後に専用リンクから行うこと

### 日時確定メール

宛先:

- To: お客様
- CC: マスター

内容:

- 予約日
- 時間枠
- キャンセルポリシー
  - 予約日の3日前までは全額返金
  - それ以降は返金なし
- キャンセル希望時はマスターからキャンセルリンクを受け取ること

### キャンセル完了メール

宛先:

- To: お客様
- CC: マスター

内容:

- キャンセルが完了したこと
- 返金有無
- 返金ありの場合は Stripe 経由で返金処理されること
- 返金なしの場合はポリシー上返金対象外であること

## 追加クラス設計

### Repository

- `includes/Repositories/class-kkpay-premium-reservation-repository.php`

責務:

- プレミアム予約テーブルのCRUD
- トークン検索
- PaymentIntent検索
- ステータス更新
- CSV用一覧取得

### Service

- `includes/Services/class-kkpay-premium-reservation-service.php`

責務:

- 決済リンク発行
- PaymentIntent 作成
- 決済確定
- 日時確定
- キャンセルリンク発行
- キャンセル・返金判定
- 既存予約テーブルへの反映

### Controller

- `includes/Controllers/class-kkpay-premium-reservation-controller.php`

責務:

- 公開AJAX
- 管理画面AJAX
- REST Webhook から呼ばれる処理の受け口

### Validator

- `includes/Validators/class-kkpay-premium-reservation-validator.php`

責務:

- 決済フォーム入力検証
- 日時確定入力検証
- キャンセル入力検証

## 追加テンプレート・アセット

テンプレート:

- `templates/premium-payment.php`
- `templates/premium-cancel.php`
- `templates/admin/premium-reservations-tab.php`

JavaScript:

- `assets/js/kkpay-premium.js`
- `assets/js/kkpay-admin-premium.js`

CSS:

- 既存 `kkpay-form.css` を基本利用する。
- 必要なら `assets/css/kkpay-premium.css` を追加する。

## 既存コードへの変更点

`early-reservation-system.php`:

- 新クラスの `require_once` を追加する。
- ショートコードを追加する。
- 公開AJAXを追加する。
- 管理AJAXを追加する。
- Webhook処理でプレミアム予約を分岐できるようにする。
- アセット読み込みを追加する。

`includes/class-kkpay-activator.php`:

- `kkpay_premium_reservations` テーブル作成を追加する。
- `KKPAY_VERSION` 更新時に `maybe_upgrade()` で作成されるようにする。

`includes/class-kkpay-admin.php`:

- 管理画面タブを追加する。
- プレミアム予約タブのレンダリングメソッドを追加する。
- 管理画面用JSの読み込みを追加する。

`includes/Services/class-kkpay-payment-service.php`:

- Webhookで `metadata[type]` が `premium_reservation` の場合、通常予約処理をスキップしてプレミアム予約処理へ渡す。

`includes/Services/class-kkpay-email-service.php`:

- プレミアム予約用メールを追加する。
- CCにマスターを入れる仕組みを追加する。

## 空席数反映

既存の空席数計算は `kkpay_reservations` を参照している。
プレミアム予約は日時確定時に `kkpay_reservations` へ作成するため、既存ロジックを大きく変えずに空席数へ反映できる。

反映席数は、決済画面でお客様が選択した `number_of_people` を使う。
日時変更時も同じ席数を維持し、変更先スロットの空席数に収まる場合のみ変更できる。

## 実装手順

1. 既存ファイルの文字化け修正方針を確定する。
2. `KKPAY_VERSION` を更新する。
3. `kkpay_premium_reservations` テーブルを追加する。
4. Repository を追加する。
5. Validator を追加する。
6. Service を追加する。
7. Controller を追加する。
8. ショートコードとテンプレートを追加する。
9. フロントエンドJSを追加する。
10. 管理画面タブを追加する。
11. 管理画面JSを追加する。
12. Stripe Webhook 分岐を追加する。
13. メール送信を追加する。
14. CSV出力を追加する。
15. 手動テストを実施する。

## テスト観点

- 決済リンクが発行できる。
- 決済リンクが24時間を過ぎると使用できない。
- 使用済み決済リンクを再利用できない。
- 言語、名前、メールアドレスが未入力の場合に決済できない。
- Stripe PaymentIntent が USD `32 * 席数` で作成される。
- 決済成功後に `paid` になる。
- 入金完了メールが送信され、マスターがCCに入る。
- 入金済み予約に日付と時間枠を設定できる。
- 日時確定・日時変更の日付は当日から1か月後まで選択できる。
- 1か月後を超える日付では日時確定・日時変更できない。
- 日時確定時に `kkpay_reservations` に作成される。
- 通常予約の空席数に反映される。
- 日時確定メールが送信される。
- 日時未確定ではキャンセルリンクを発行できない。
- キャンセルリンクを発行できる。
- キャンセルリンクは一度しか使えない。
- 予約日の3日前までは返金される。
- 予約日の3日前を過ぎると返金されない。
- キャンセル後、通常予約とプレミアム予約の両方がキャンセル状態になる。
- キャンセル完了メールが送信される。
- 通常予約の既存決済・既存キャンセルに影響しない。
