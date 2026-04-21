# KichiKichi Paid Reservation

## 目的

[KichiKichi](https://kichikichi.com/)の既存WordPressサイトに、3日前から予約できる事前決済付きの「プレミアム予約」機能をWordPressプラグインとして追加する。

この機能は、現在稼働中の当日予約システムとは別の予約導線として提供する。お客様は来店前に席料金をStripeでカード決済し、来店当日は通常どおり店内で食事を注文する。席料金は食事代とは別で、キャンセル時の返金は行わない。

## 前提

- 対象サイト: WordPressで運用中の `kichikichi.com`
- 実装形態: WordPressプラグイン
- バックエンド: PHP / WordPress API / MySQL
- フロントエンド: HTML / CSS / JavaScript
- 決済: Stripe
- メール: WordPressのメール送信機能を利用し、必要に応じてSMTPプラグインと連携
- タイムゾーン: Asia/Tokyo
- 既存の当日予約システムは継続利用する

## 現行システムの把握

`現行の動作.txt`に記載された現行コードでは、主に以下の機能が稼働している。

- 当日予約フォーム: `[reservation_form]`
- 予約確認フォーム: `[confirm_reservation_form]`
- 営業日カレンダー: `[customer_calendar]`
- 管理画面からの予約開始操作
- 時間枠ごとのテーブル席・カウンター席上限管理
- 予約済み人数による空席判定
- 営業日カレンダーの管理
- AJAXによる予約登録、残席確認、予約確認、キャンセル処理

現行の時間枠は以下の6枠で運用されている。

| 枠 | 到着時間 | 席利用時間 | 区分 |
| --- | --- | --- | --- |
| slot_1 | 11:40 | 12:00-13:00 | Lunch |
| slot_2 | 12:40 | 13:00-14:00 | Lunch |
| slot_3 | 16:40 | 17:00-18:00 | Dinner |
| slot_4 | 17:40 | 18:00-19:00 | Dinner |
| slot_5 | 18:40 | 19:00-20:00 | Dinner |
| slot_6 | 19:40 | 20:00-21:00 | Dinner |

プレミアム予約でも、この時間枠を基本として利用する。ただし、当日予約とは別在庫として扱うか、共通在庫から差し引くかは管理設定で選べる設計とする。

## 業務要件

### プレミアム予約の基本条件

- 予約可能日は、来店日の3日前からとする。
- 席料金は1席あたり3,000円とする。
- 決済金額は `予約人数 x 3,000円` とする。
- 食事代は席料金に含めない。
- 来店当日は通常どおり店内で注文・会計する。
- 決済完了後に予約を確定する。
- 決済未完了の予約は確定予約として扱わない。
- キャンセルしても席料金は返金しない。
- お客様向け画面に「プレミアム予約できる日」を明記する。

### 予約受付期間

「3日前から可能」は、以下のルールで定義する。

- 今日を含めず、来店日の3日前 00:00 JST から受付開始する。
- 例: 2026年4月20日に来店する予約は、2026年4月17日 00:00 JST から受付可能。
- 受付終了は、原則として来店日前日 23:59 JST または店舗が管理画面で設定した締切時刻とする。
- 当日枠は既存の当日予約システムに任せる。

### 予約可能日の表示

お客様向け画面では、以下を明確に表示する。

- プレミアム予約受付中の日付
- 受付前の日付
- 満席の日付
- 休業日
- ランチのみ営業、ディナーのみ営業、両方営業
- 「席料金は1席3,000円、食事代別、キャンセル返金なし」の注意事項

既存の営業日カレンダー `wp_calendar` の `date`, `lunch`, `dinner` を参照し、営業していない時間帯は選択不可にする。

### 決済

- Stripe Checkoutを利用する。
- 通貨はJPY。
- 商品名は「KichiKichi Premium Reservation Seat Fee」など、席料金であることが分かる名称にする。
- Stripe Checkout Session作成時点では仮予約を作成する。
- Stripe Webhookの `checkout.session.completed` を受信した時点で予約を確定する。
- Webhook検証にはStripe署名シークレットを使用する。
- 返金はシステム上では自動実行しない。
- 管理者が特例返金する場合はStripeダッシュボードで手動対応する。

### メール

決済完了後に予約完了メールを送信する。

送信先:

- お客様
- 店舗管理者

お客様向けメールに含める内容:

- 予約番号
- 氏名
- メールアドレス
- 来店日
- 到着時間
- 席利用時間
- 人数
- 席種別
- 決済済み席料金
- 食事代は別であること
- キャンセルしても返金されないこと
- 来店時の注意事項

店舗向けメールに含める内容:

- 予約番号
- 予約日時
- 氏名
- メールアドレス
- 来店日
- 時間枠
- 人数
- 席種別
- Stripe Payment Intent ID
- Stripe Checkout Session ID

### 管理画面

WordPress管理画面に「プレミアム予約」メニューを追加する。

必要な管理機能:

- プレミアム予約一覧
- 日付、時間枠、氏名、メールアドレスでの検索
- 予約ステータスの確認
- Stripe決済IDの確認
- 予約メモの登録
- 管理者によるキャンセル扱いへの変更
- プレミアム予約の受付ON/OFF
- 受付締切時刻の設定
- 1席料金の設定
- 時間枠ごとのプレミアム予約枠数設定
- 当日予約と在庫を共通化するか、別管理するかの設定

## 機能要件

### お客様向け機能

1. プレミアム予約カレンダー表示
2. 来店日選択
3. 時間枠選択
4. 席種別選択
5. 人数選択
6. 氏名・メールアドレス入力
7. 注意事項同意チェック
8. Stripe Checkoutへの遷移
9. 決済完了後の予約完了画面表示
10. 予約完了メール受信

### 管理者向け機能

1. 予約一覧閲覧
2. 予約詳細確認
3. 予約ステータス変更
4. プレミアム予約枠数設定
5. 受付可能日の確認
6. Stripe決済情報確認
7. 予約完了メールの再送
8. CSVエクスポート

### システム機能

1. 受付可能日判定
2. 営業日判定
3. 残席判定
4. 仮予約作成
5. Stripe Checkout Session作成
6. Stripe Webhook受信
7. 決済完了後の予約確定
8. メール送信
9. 期限切れ仮予約の自動失効
10. 操作ログ・エラーログ記録

## 非機能要件

### セキュリティ

- すべてのフォームにWordPress nonceを付与する。
- AJAX/APIの入力値は `sanitize_text_field`, `sanitize_email`, `absint` などで検証する。
- SQLは `$wpdb->prepare()` を使用する。
- Stripe秘密鍵はDBに平文保存せず、可能であれば `wp-config.php` の定数で管理する。
- WebhookはStripe署名で検証する。
- 管理機能は `manage_options` 権限以上に限定する。
- お客様の個人情報をフロントエンドに不要に露出しない。

### 信頼性

- 決済完了前の予約は `pending_payment` として扱い、残席の一時確保期限を設ける。
- Webhookが複数回来ても二重確定しないよう、Stripe Session IDにユニーク制約を設ける。
- メール送信失敗時も予約確定は維持し、管理画面にメール送信ステータスを表示する。
- 仮予約の期限切れ処理をWP-Cronで実行する。

### パフォーマンス

- 予約一覧にはページネーションを実装する。
- 残席確認クエリには `reservation_date`, `time_slot`, `status` のインデックスを設定する。
- カレンダー取得は月単位で取得する。

### 多言語

現行システムが英語、日本語、韓国語、中国語に対応しているため、プレミアム予約も以下の言語対応を想定する。

- English
- 日本語
- 한국어
- 简体中文
- 繁體中文

初期実装では、最低限英語と日本語の文言を用意し、文言定義を拡張可能にする。

## 基本設計

### プラグイン構成案

```text
kichikichi-paid-reservation/
  kichikichi-paid-reservation.php
  includes/
    class-plugin.php
    class-activator.php
    class-admin.php
    class-frontend.php
    class-reservation-service.php
    class-stripe-service.php
    class-mailer.php
    class-calendar-service.php
    class-cron.php
  templates/
    premium-reservation-form.php
    premium-reservation-complete.php
    admin-reservations.php
    admin-settings.php
  assets/
    css/
      premium-reservation.css
      admin.css
    js/
      premium-reservation.js
      admin.js
  languages/
```

### ショートコード

| ショートコード | 用途 |
| --- | --- |
| `[kichikichi_premium_reservation]` | お客様向けプレミアム予約フォーム |
| `[kichikichi_premium_reservation_complete]` | Stripe決済完了後の完了画面 |

### REST API / AJAX

WordPress REST APIで実装することを推奨する。既存システムが `admin-ajax.php` を利用しているため、既存実装との統一を優先する場合はAJAXでもよい。

| エンドポイント | 用途 |
| --- | --- |
| `GET /premium-reservation/calendar` | 月単位の営業日・予約可能日取得 |
| `GET /premium-reservation/slots` | 指定日の時間枠と残席取得 |
| `POST /premium-reservation/checkout` | 仮予約作成とStripe Checkout Session作成 |
| `POST /premium-reservation/webhook/stripe` | Stripe Webhook受信 |
| `POST /premium-reservation/resend-mail` | 管理者によるメール再送 |

### データベース設計

#### `wp_kichikichi_premium_reservations`

プレミアム予約本体を保存する。

| カラム | 型 | 内容 |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | 内部ID |
| `reservation_code` | VARCHAR(32) UNIQUE | お客様向け予約番号 |
| `reservation_date` | DATE | 来店日 |
| `time_slot` | VARCHAR(20) | `slot_1` など |
| `arrival_time` | TIME | 到着時間 |
| `seating_start` | TIME | 席利用開始 |
| `seating_end` | TIME | 席利用終了 |
| `seating_preference` | VARCHAR(20) | `Table` または `Bar` |
| `number_of_people` | INT UNSIGNED | 人数 |
| `seat_fee_unit` | INT UNSIGNED | 1席料金 |
| `amount_total` | INT UNSIGNED | 合計決済額 |
| `currency` | VARCHAR(3) | `JPY` |
| `customer_name` | VARCHAR(191) | 氏名 |
| `customer_email` | VARCHAR(191) | メール |
| `language` | VARCHAR(10) | 表示言語 |
| `status` | VARCHAR(30) | 予約ステータス |
| `stripe_checkout_session_id` | VARCHAR(255) UNIQUE | Checkout Session ID |
| `stripe_payment_intent_id` | VARCHAR(255) | Payment Intent ID |
| `payment_status` | VARCHAR(30) | 決済ステータス |
| `mail_sent_at` | DATETIME NULL | メール送信日時 |
| `admin_note` | TEXT NULL | 管理メモ |
| `created_at` | DATETIME | 作成日時 |
| `updated_at` | DATETIME | 更新日時 |
| `expires_at` | DATETIME NULL | 仮予約期限 |

主なインデックス:

- `reservation_date`
- `time_slot`
- `status`
- `reservation_date, time_slot, seating_preference, status`
- `customer_email`
- `stripe_checkout_session_id`

#### `wp_kichikichi_premium_slot_limits`

時間枠ごとのプレミアム予約枠数を保存する。

| カラム | 型 | 内容 |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | 内部ID |
| `time_slot` | VARCHAR(20) UNIQUE | `slot_1` など |
| `table_limit` | INT UNSIGNED | テーブル席上限 |
| `bar_limit` | INT UNSIGNED | カウンター席上限 |
| `enabled` | TINYINT(1) | 受付有効フラグ |
| `created_at` | DATETIME | 作成日時 |
| `updated_at` | DATETIME | 更新日時 |

#### `wp_kichikichi_premium_logs`

決済・メール・管理操作ログを保存する。

| カラム | 型 | 内容 |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | 内部ID |
| `reservation_id` | BIGINT UNSIGNED NULL | 関連予約ID |
| `type` | VARCHAR(50) | `payment`, `mail`, `admin`, `webhook` など |
| `message` | TEXT | ログ内容 |
| `context_json` | LONGTEXT NULL | 補足情報 |
| `created_at` | DATETIME | 作成日時 |

### ステータス設計

| ステータス | 意味 |
| --- | --- |
| `pending_payment` | Stripe決済前の仮予約 |
| `confirmed` | 決済完了・予約確定 |
| `expired` | 決済未完了で期限切れ |
| `cancelled_no_refund` | キャンセル済み・返金なし |
| `failed` | 決済または処理失敗 |

残席計算では、原則として `pending_payment` と `confirmed` を席数に含める。ただし `pending_payment` は `expires_at` を過ぎたら除外する。

### 予約可能判定

指定日が予約可能かどうかは、以下の順で判定する。

1. WordPressの現在時刻をJSTで取得する。
2. 指定日が今日以前なら不可。
3. 指定日の3日前 00:00 JST に達していなければ不可。
4. 管理設定の受付締切を過ぎていれば不可。
5. `wp_calendar` を参照し、休業日なら不可。
6. ランチ枠の場合は `lunch = 1`、ディナー枠の場合は `dinner = 1` であることを確認する。
7. プレミアム予約枠が有効であることを確認する。
8. 残席数が希望人数以上あることを確認する。

### 残席計算

基本式:

```text
残席 = 設定上限 - 確定済み予約人数 - 有効な仮予約人数
```

共通在庫モードの場合:

```text
残席 = 共通上限 - 当日予約の予約人数 - プレミアム確定予約人数 - 有効なプレミアム仮予約人数
```

別在庫モードの場合:

```text
残席 = プレミアム予約上限 - プレミアム確定予約人数 - 有効なプレミアム仮予約人数
```

初期導入では、既存当日予約への影響を抑えるため、別在庫モードを推奨する。店舗運用が安定した後、共通在庫モードを検討する。

### Stripe決済フロー

1. お客様が日付、時間枠、席種別、人数、氏名、メールアドレスを入力する。
2. フロントエンドが残席確認を行う。
3. サーバー側で再度、営業日・受付期間・残席・入力値を検証する。
4. `pending_payment` の仮予約を作成する。
5. Stripe Checkout Sessionを作成する。
6. お客様をStripe Checkoutへリダイレクトする。
7. 決済完了後、StripeがWebhookを送信する。
8. Webhookで署名検証を行う。
9. `checkout.session.completed` を受け取ったら予約を `confirmed` に更新する。
10. 予約完了メールを送信する。
11. 完了画面で予約番号と予約内容を表示する。

### メール送信設計

メール送信は `KichiKichi_Premium_Mailer` のような専用クラスに集約する。

推奨するメソッド:

- `send_customer_confirmation($reservation_id)`
- `send_admin_notification($reservation_id)`
- `resend_confirmation($reservation_id)`

メール本文はテンプレート化し、HTMLメールとプレーンテキストの両方に対応できる構成にする。

### 管理画面設計

管理画面は以下のタブで構成する。

| タブ | 内容 |
| --- | --- |
| 予約一覧 | プレミアム予約の検索・詳細確認 |
| 枠設定 | 時間枠ごとのプレミアム予約数設定 |
| 決済設定 | Stripe公開鍵、秘密鍵、Webhookシークレット |
| メール設定 | 管理者通知先、メール件名、送信者名 |
| 運用設定 | 受付ON/OFF、受付締切、在庫モード |
| ログ | Webhook、メール、管理操作のログ |

Stripe秘密鍵・Webhookシークレットは管理画面に表示しない。保存後はマスク表示にする。

## 画面設計

### お客様向け予約フォーム

表示要素:

- プレミアム予約説明
- 予約可能日カレンダー
- 日付選択
- 時間枠選択
- 席種別選択
- 人数選択
- 氏名
- メールアドレス
- 注意事項同意チェック
- 決済へ進むボタン

必須注意文:

```text
Premium reservation seat fee is 3,000 JPY per person.
Food and drinks are not included.
Payment is required in advance by credit card.
No refunds will be made for cancellations.
Please order food and drinks at the restaurant as usual on the day of your visit.
```

日本語:

```text
プレミアム予約の席料金は1席3,000円です。
お食事代・お飲み物代は含まれていません。
席料金は事前にカード決済されます。
キャンセルの場合も返金はありません。
ご来店当日は通常どおり店内でご注文ください。
```

### 完了画面

表示要素:

- 予約完了メッセージ
- 予約番号
- 来店日
- 時間枠
- 人数
- 決済済み金額
- メールを送信済みであること
- キャンセル返金なしの再掲

## 既存当日予約システムとの連携方針

既存システムは当日の短時間受付に特化しているため、プレミアム予約は別プラグインまたは同一プラグイン内の別モジュールとして実装する。

連携するもの:

- 営業日カレンダー `wp_calendar`
- 既存の時間枠定義
- 必要に応じた席数上限

分離するもの:

- 予約テーブル
- 決済状態
- メール送信履歴
- キャンセルポリシー
- 管理画面の予約一覧

この分離により、既存の当日予約運用を壊さずにプレミアム予約を段階導入できる。

## 実装時の注意点

- Stripe Checkout Session作成前後で必ずサーバー側の残席再確認を行う。
- 決済完了はリダイレクトURLではなくWebhookを正とする。
- Webhookは同じイベントが複数回来る前提で冪等に処理する。
- 仮予約の期限は15分程度を初期値とする。
- メール送信に失敗しても予約確定処理をロールバックしない。
- 予約番号は推測しにくい値にする。
- キャンセル操作は返金しないことを管理画面でも明示する。
- 法務・店舗運用上、返金なしポリシーの文言は公開前に店舗側で最終確認する。

## 導入手順案

1. 開発環境にWordPressを用意する。
2. 既存当日予約システムと同じ営業日カレンダーを利用できる状態にする。
3. 本プラグインを `wp-content/plugins/kichikichi-paid-reservation` に配置する。
4. Stripeテスト環境の公開鍵、秘密鍵、Webhookシークレットを設定する。
5. プレミアム予約枠数を設定する。
6. テスト決済で予約作成、Webhook、メール送信を確認する。
7. 店舗側で文言、料金、キャンセルポリシーを確認する。
8. Stripe本番キーへ切り替える。
9. 本番サイトで短期間の限定枠から運用開始する。

## 受け入れ条件

- 3日前になっていない日付は予約できない。
- 休業日は予約できない。
- ランチ営業なしの日にランチ枠を予約できない。
- ディナー営業なしの日にディナー枠を予約できない。
- 残席を超える人数では予約できない。
- Stripe決済未完了では予約確定にならない。
- Stripe決済完了Webhook受信後に予約が確定する。
- お客様に予約完了メールが送信される。
- 店舗管理者に予約通知メールが送信される。
- キャンセル時に自動返金されない。
- 管理画面で予約内容とStripe決済IDを確認できる。
- 既存の当日予約フォームが従来どおり動作する。

## 未確定事項

実装前に店舗側と確認する事項:

- プレミアム予約枠を当日予約枠と共通にするか、別枠にするか
- 予約受付終了時刻
- 最大予約人数
- テーブル席・カウンター席をお客様に選ばせるか、店舗側で割り当てるか
- キャンセル受付フォームを設けるか
- 返金なしポリシーの最終文言
- メール本文の正式な多言語文言
- Stripeアカウントの本番運用担当者

