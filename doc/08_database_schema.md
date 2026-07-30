# データベーススキーマ

## テーブル一覧

| テーブル名 | 説明 | 作成者 |
|-----------|------|-------|
| `{prefix}kkpay_holds` | 5 分間の仮予約（ホールド） | このプラグイン |
| `{prefix}kkpay_reservations` | 確定済み予約 | このプラグイン |
| `{prefix}kkpay_cancellations` | キャンセル履歴 | このプラグイン |
| `{prefix}kkpay_accepted_dates` | プレミアム予約の受付日程・スロット別席数管理 | このプラグイン |
| `{prefix}calendar` | 営業カレンダー（読み取り専用） | KichiKichi Calendar プラグイン |

テーブルは `class-kkpay-activator.php` の `KKPAY_Activator::activate()` が `dbDelta()` で作成します。

---

## kkpay_holds

仮予約（ホールド）を保持するテーブルです。  
ユーザーが予約フォームを送信してから 5 分間だけ席を確保します。  
決済完了後 or 期限切れ後に削除されます。

| カラム | 型 | NOT NULL | デフォルト | 説明 |
|--------|-----|---------|----------|------|
| `id` | BIGINT UNSIGNED | ✅ | AUTO_INCREMENT | 主キー |
| `reservation_date` | DATE | ✅ | - | 予約日 |
| `time_slot` | VARCHAR(10) | ✅ | - | スロットキー（`slot_1`〜`slot_6`） |
| `number_of_people` | TINYINT UNSIGNED | ✅ | 1 | 人数（1〜4） |
| `name` | VARCHAR(100) | ✅ | - | 氏名 |
| `email` | VARCHAR(200) | ✅ | - | メールアドレス |
| `language` | VARCHAR(10) | ✅ | `'en'` | 言語コード |
| `hold_token` | VARCHAR(64) | ✅ | - | セッション識別子（UNIQUE） |
| `expires_at` | DATETIME | ✅ | - | 有効期限（作成から +5 分） |
| `created_at` | DATETIME | ✅ | - | 作成日時 |

**インデックス：**
- `PRIMARY KEY (id)`
- `UNIQUE KEY hold_token (hold_token)`

**設計意図：**
- `hold_token` は `bin2hex(random_bytes(32))` で生成する 64 文字のランダム文字列。推測不可能で衝突しない。
- `expires_at > NOW()` を WHERE に加えることで有効なホールドだけを取得できる。
- Cron が 1 分ごとに `expires_at < NOW()` の行を削除するため、テーブルは肥大化しない。

---

## kkpay_reservations

決済完了後の確定済み予約を保持するテーブルです。  
キャンセル後も削除せず `cancelled_at` を SET して履歴を残します。

| カラム | 型 | NOT NULL | デフォルト | 説明 |
|--------|-----|---------|----------|------|
| `id` | BIGINT UNSIGNED | ✅ | AUTO_INCREMENT | 主キー |
| `hold_id` | BIGINT UNSIGNED | ❌（NULL 可） | NULL | 元ホールドの ID（参照のみ） |
| `reservation_date` | DATE | ✅ | - | 予約日 |
| `time_slot` | VARCHAR(10) | ✅ | - | スロットキー |
| `name` | VARCHAR(100) | ✅ | - | 氏名 |
| `email` | VARCHAR(200) | ✅ | - | メールアドレス |
| `language` | VARCHAR(10) | ✅ | `'en'` | 言語コード |
| `stripe_payment_intent_id` | VARCHAR(255) | ✅ | - | Stripe PaymentIntent ID |
| `stripe_charge_id` | VARCHAR(255) | ❌（NULL 可） | NULL | Stripe Charge ID |
| `payment_status` | ENUM | ✅ | `'pending'` | `pending` / `paid` / `refunded` |
| `amount` | INT | ✅ | 3000 | 支払い金額（日本円） |
| `number_of_people` | TINYINT UNSIGNED | ✅ | 1 | 人数 |
| `created_at` | DATETIME | ✅ | - | 予約確定日時 |
| `cancelled_at` | DATETIME | ❌（NULL 可） | NULL | キャンセル日時（NULL = 有効） |

**インデックス：**
- `PRIMARY KEY (id)`
- `UNIQUE KEY email_date_slot (email(191), reservation_date, time_slot)`
- `KEY payment_intent (stripe_payment_intent_id(191))`

**設計意図：**

`UNIQUE KEY (email, reservation_date, time_slot)` は 1 人が同じ日・同じスロットに二重予約することを DB レベルで防ぎます。  
これにより `create_from_hold()` の冪等性が保証されます（INSERT 失敗時に既存レコードを返す）。

`stripe_charge_id` が NULL になるケース：  
Webhook が PaymentIntent.succeeded で受信した時点ではまだ `latest_charge` が確定していない場合があります。  
通常のキャンセル処理では返金を行わないため、`stripe_charge_id` を使って Stripe 返金 API を呼び出すことはありません。

**payment_status の遷移：**
```
pending → paid       （決済成功）
paid    → refunded   （Stripe ダッシュボード等で外部返金された場合）
```
`pending` のまま残ることは通常ありませんが、Webhook が先に到達した場合の中間状態として存在しえます。

---

## kkpay_cancellations

キャンセルの監査ログ（Audit Trail）です。  
`reservations` テーブルは `cancelled_at` を UPDATE するだけですが、  
このテーブルには返金なしでキャンセルされたことを示す `refund_status = 'none'` と `refund_amount = 0` を残します。

| カラム | 型 | NOT NULL | デフォルト | 説明 |
|--------|-----|---------|----------|------|
| `id` | BIGINT UNSIGNED | ✅ | AUTO_INCREMENT | 主キー |
| `reservation_id` | BIGINT UNSIGNED | ✅ | - | 対応する予約 ID |
| `cancelled_at` | DATETIME | ✅ | - | キャンセル実行日時（JST） |
| `refund_status` | ENUM | ✅ | `'none'` | 通常キャンセルでは `none` |
| `stripe_refund_id` | VARCHAR(255) | ❌（NULL 可） | NULL | 通常キャンセルでは NULL |
| `refund_amount` | INT | ✅ | 0 | 通常キャンセルでは 0 |

**インデックス：**
- `PRIMARY KEY (id)`
- `KEY reservation_id (reservation_id)`

**設計意図：**
- `reservations.cancelled_at` だけではキャンセル処理の監査情報が不足する。
- このテーブルを見ることで「返金なし」でキャンセルされた証跡を追跡できる。
- 現状は 1 予約に対して 1 行が想定されている。

---

## kkpay_accepted_dates

プレミアム予約モードで使用するテーブルです。  
管理画面から管理者が「どの日程・スロットを受付対象にするか」と「スロットごとの席数上限」を設定します。  
このテーブルに 1 件でもレコードが存在する場合、プラグイン全体が**プレミアムモード**で動作します。

| カラム | 型 | NOT NULL | デフォルト | 説明 |
|--------|-----|---------|----------|------|
| `id` | BIGINT UNSIGNED | ✅ | AUTO_INCREMENT | 主キー |
| `reservation_date` | DATE | ✅ | - | 受付対象日 |
| `time_slot` | VARCHAR(20) | ✅ | - | スロットキー（`slot_1`〜`slot_6`） |
| `capacity` | TINYINT UNSIGNED | ✅ | 8 | スロットの席数上限（`KKPAY_MAX_CAPACITY` にフォールバック） |
| `enabled` | TINYINT(1) UNSIGNED | ✅ | 1 | 1 = 受付有効、0 = 受付停止 |
| `created_at` | DATETIME | ✅ | - | 作成日時 |
| `updated_at` | DATETIME | ✅ | - | 更新日時 |

**インデックス：**
- `PRIMARY KEY (id)`
- `UNIQUE KEY date_slot (reservation_date, time_slot)`
- `KEY reservation_date (reservation_date)`

**設計意図：**

- レコードが 1 件でも存在すると `KKPAY_Calendar_Service::is_accepting_reservations()` がプレミアムモードに切り替わる。
- プレミアムモードでは `enabled = 1` のレコードがある日程・スロットだけが予約可能になる。受付開始は**対象日の 3 日前 0:00 JST**（時刻ベースのルールは適用されない）。
- 通常モード（レコードなし）では `KKPAY_ACCEPT_HOUR_JST`（13 時）が受付開始時刻を制御する。
- `capacity` は通常モード・プレミアムモードどちらでも常に参照される。レコードがないスロットは `KKPAY_MAX_CAPACITY` にフォールバックする。
- 管理画面の「席数設定」タブから `upsert_slot()` 経由で `capacity` と `enabled` を登録・更新する。

---

## calendar（外部テーブル・読み取り専用）

「KichiKichi Reservation System」プラグインが管理するテーブルです。  
**このプラグインは読み取りのみ行い、書き込みは絶対に行いません。**

| カラム | 型 | 説明 |
|--------|-----|------|
| `date` | DATE | 対象日 |
| `lunch` | TINYINT(1) | 1 = ランチ営業あり |
| `dinner` | TINYINT(1) | 1 = ディナー営業あり |

---

## テーブル間の関係

```
kkpay_holds ──── (hold_token) ────▶  Stripe PaymentIntent
                                               │
                                               ▼
kkpay_holds ──── (hold_id) ───────▶ kkpay_reservations
                                               │
                                               ▼
                                    kkpay_cancellations
                                    (reservation_id)

calendar ──── (date 参照) ─────────▶ kkpay_reservations
                                    （外部キー制約なし・論理参照のみ）
```

**外部キー制約は設定していません。** WordPress のテーブルは `$wpdb` が InnoDBの外部キーを前提としないため、アプリケーション層でリレーションを管理します。

---

## スキーマ変更時の注意事項

1. `dbDelta()` は**カラムの追加**と**インデックスの追加**のみ安全に行えます。
2. カラムの型変更・削除・インデックスの変更は `dbDelta()` では行えません。手動で ALTER TABLE が必要です。
3. `KKPAY_VERSION` 定数を上げるとバージョン管理できますが、現状は `dbDelta()` が常に実行される構成です。
4. 本番環境でのマイグレーションは必ずバックアップを取ってから行ってください。

## Event Reservation（開催回管理）

イベント予約は通常予約・スペシャルプレミアム予約とは別テーブルで管理する。日時はすべて Asia/Tokyo の値として保存・比較する。

### `kkpay_events`

開催回の親テーブル。`title`、固定単価 `unit_amount`（50 USD）、`currency`、`status`（`draft` / `open` / `closed` / `archived`）を保持する。公開受付できる `open` は全体で1件だけとし、サービス層の名前付きロックとトランザクション内の再確認で制御する。`migration_key` は管理画面の二重送信を冪等に処理する内部キーである。

### `kkpay_event_slots`

開催回ごとの日時・定員マスタ。`event_id` は必須で、`UNIQUE (event_id, event_date, event_time)` により同一開催回内の重複枠を防ぐ。異なる開催回では同じ日時を登録できる。

### `kkpay_event_holds`

決済中の5分間の席確保を保持する。開催回は `slot_id -> kkpay_event_slots.event_id` から決まる。残席計算ではJSTで生成した現在時刻と `expires_at` を比較し、`HELD` の有効ホールドだけを加算する。

### `kkpay_event_reservations`

決済確定済み予約。`payment_intent_id` と `reservation_code` は一意。開催回は `slot_id -> kkpay_event_slots.event_id` から決まる。キャンセルは `reservation_status = CANCELED` と日時を記録し、Stripe返金は実行しない。

### `kkpay_event_reservation_events`

ホールド、決済確定、キャンセル、外部返金同期などの監査ログ。予約作成前の事象も記録できるよう `reservation_id` はNULLを許容する。

### 関係

`kkpay_events (1) -> kkpay_event_slots (N) -> kkpay_event_holds / kkpay_event_reservations (N)`。MySQLの外部キー制約は使用していないため、デプロイ時は `doc/24_event_management_operations.md` の孤児データ確認SQLを実行する。
