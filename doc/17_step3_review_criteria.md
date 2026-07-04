# Step 3 レビュー観点

Step 3（プレミアム予約・スペシャルプレミアム予約の `Bar` 固定化）のコードレビューで確認すべき観点をまとめたドキュメント。
根拠ドキュメント: `doc/00_overview.md`、`doc/01_directory_structure.md`、`doc/14_same_day_reservation_integration_design.md`、`doc/15_same_day_reservation_current_spec.md`

---

## 1. アーキテクチャ観点（doc/00・doc/01 より）

### 1-1. 層の配置と依存方向

- `KKPAY_Capacity_Service` が依存してよいのは Repository 層のみ（`KKPAY_Slot_Capacity_Repository`、`KKPAY_Reservation_Repository`、`KKPAY_Hold_Repository`）
- Service 層同士の横断呼び出しは `KKPAY_Reservation_Service` → `KKPAY_Capacity_Service` の方向のみ許容
- Repository 層が Service 層を呼び出していないこと（循環依存の禁止）

### 1-2. `require_once` 読み込み順

- `early-reservation-system.php` の読み込み順は `Infrastructure → Repositories → Services → Validators → Controllers` の順を守ること
- Step 3 で変更する `class-kkpay-hold-service.php`・`class-kkpay-reservation-service.php`・`class-kkpay-cancellation-service.php`・`class-kkpay-premium-reservation-service.php` が `KKPAY_Capacity_Service` よりも **後に** 読み込まれること

### 1-3. ファイル・クラス命名

- 既存ファイルへの追加のため命名違反は起きにくいが、新規メソッド名が `snake_case` になっていること

### 1-4. コーディング規約（CLAUDE.md・doc/11 より）

- `if ( ! defined( 'ABSPATH' ) ) { exit; }` がファイル先頭に存在すること
- 配列構文: `array()` を使い `[]` を使わないこと
- インデント: 4 スペース（タブ禁止）
- 返り値契約: 成功 → 値、失敗 → `WP_Error`。`false` や `0` でエラーを表現しないこと
- ファイル末尾に `?>` 閉じタグを置かないこと

---

## 2. 設計仕様観点（doc/14 PR 3 より）

### 2-1. プレミアム予約の `seating_preference = 'Bar'` 固定【Critical】

- `KKPAY_Reservation_Service::create_from_hold()` の INSERT に `seating_preference = 'Bar'` が含まれていること
- `kkpay_reservations.seating_preference` の DB デフォルトは `NULL`（`class-kkpay-activator.php` の DDL で確認可）。明示的に `'Bar'` を渡さなければ `NULL` で保存される
- `seating_preference = NULL` のまま保存されると、`sum_active_people_for_slot_and_seat('Bar')` がこの予約をカウントせず、Bar 残席が実態より多く見える
- HoldService→CapacityService による空席チェックは `'Bar'` として計算するが、確定後の予約レコードが `NULL` のままではカウントが一致しなくなる

### 2-2. スペシャルプレミアム予約の `seating_preference = 'Bar'` 固定

- `create_special_premium_from_premium()` の INSERT に `seating_preference = 'Bar'` が含まれていること
- `status = 'active'` が含まれていること（DB デフォルトも `'active'` だが、明示することで意図を示す）
- `reschedule_special_premium()` で日時変更後も `seating_preference` が `'Bar'` のまま維持されること

### 2-3. 日時確定・日時変更時の共通空席ロックへの接続

- `create_special_premium_from_premium()` が `KKPAY_Capacity_Service::check_available_for_update()` を呼んでいること
- `reschedule_special_premium()` が `KKPAY_Capacity_Service::check_available_for_update_excluding_reservation()` を呼んでいること（自分自身の席数を除外して空席チェック）
- どちらもオープン中のトランザクション内で呼ばれていること（`FOR UPDATE` ロックの契約）

### 2-4. 日時変更時の自己除外ロジック

- 変更前と変更後のスロットが同一の場合でも `sum_active_people_for_slot_and_seat_excluding_id()` が自分の席数を除外して計算していること
- 除外対象は `reservation_id`（`kkpay_reservations.id`）であり、予約人数を減算ではなく SQL 側で除外していること

### 2-5. トランザクション境界とイベントログの整合性

- 予約作成・日時変更・キャンセルの各パスで、イベントログ（`KKPAY_Reservation_Event_Repository::insert()`）が `COMMIT` より **前** に呼ばれていること
- イベントログの INSERT が失敗した場合に `ROLLBACK` してエラーを返すこと
- `create_from_hold()` のように自前のトランザクションを持たないメソッドでイベントログが fire-and-forget になっている場合、その判断が意図的であることがコメントで明示されているか、または `START TRANSACTION` を追加してトランザクション保護していること

### 2-6. 空席チェックのエラーコード伝播

- `KKPAY_Capacity_Service::check_available_for_update()` が返す `WP_Error` のコードには `capacity_not_configured`・`slot_unavailable`・`capacity_exceeded` の3種類がある
- これらを呼び出し元で一律に別のエラーコードへ変換している場合、`error_log()` で元のコードを残すなど、診断可能な形になっていること
- 特に `slot_unavailable`（受付枠未設定）と `capacity_exceeded`（満席）は運用上の意味が異なるため、変換する場合はその理由が明確なこと

### 2-7. イベントペイロードの内容

- `event_type` が `'reservation_created'`・`'reservation_rescheduled'`・`'reservation_cancelled'` のいずれかであること（doc/14 の `event_type` 一覧と一致していること）
- `actor_type` が `'customer'`・`'admin'`・`'system'`・`'stripe'` のいずれかであること
- `event_payload` が `array()` として渡され、Repository 側で `wp_json_encode()` して `LONGTEXT` に保存していること
- 日時変更イベントに変更前後のスロット情報（`from`・`to`）が含まれていること

### 2-8. `get_remaining_capacity()` の参照先変更

- `kkpay_accepted_dates` ではなく `kkpay_slot_capacities` を参照していること
- `enabled = 0` または行が存在しない場合に `0` を返していること
- `sum_active_people_for_slot_and_seat('Bar')` で `seating_preference = 'Bar'` に絞って確定席数を計算していること
- `KKPAY_Hold_Repository::sum_people_for_slot_and_seat()` / `sum_people_for_slot_and_seat_with_lock()` で `seating_preference = 'Bar'` に絞って有効ホールド席数を計算していること

---

## 3. 前ステップとの依存関係（doc/14 PR 2 → PR 3 の繋がり）

### 3-1. `kkpay_slot_capacities` テーブルへの依存

- `KKPAY_Capacity_Service::check_available_for_update()` は `kkpay_slot_capacities` が存在することを前提とする
- Step 1 未適用の環境では SQL エラーになるため、README またはスクリプトのコメントに Step 1 前提が記載されていること

### 3-2. `kkpay_reservation_events` テーブルへの依存

- Step 3 で追加する全イベントログ INSERT は `kkpay_reservation_events` が存在することを前提とする
- Step 1 未適用の環境でイベントログ INSERT が失敗した場合、ROLLBACK されてキャンセルや日時確定が失敗する（ブロッキング）

### 3-3. `kkpay_reservations.seating_preference` バックフィルへの依存

- Step 1 のバックフィルにより、既存の `kkpay_reservations` の `seating_preference` は `NULL` でなく `'Bar'` になっているはず
- `sum_active_people_for_slot_and_seat('Bar')` が既存の有料予約を正しくカウントするためには、このバックフィルが完了していることが前提
- バックフィル前の環境でこの計算を実行すると、既存予約が除外されて Bar 残席が多く見える

### 3-4. `KKPAY_Capacity_Service` のロード順

- Step 2 で追加した `class-kkpay-capacity-service.php` が Step 3 の各サービスより **前に** `require_once` されていること
- `KKPAY_Hold_Service`・`KKPAY_Reservation_Service`・`KKPAY_Cancellation_Service`・`KKPAY_Premium_Reservation_Service` が `KKPAY_Capacity_Service` に依存するため、読み込み順の逆転がないこと

---

## 4. 既存機能への影響確認（doc/15 より）

### 4-1. 既存のスペシャルプレミアム予約フローへの影響

- 決済リンク発行・入金フローはコードを変更しておらず、影響がないこと
- 日時確定（`ajax_schedule_reservation`）が共通ロックサービスに接続された後も、正常系は従来通りに動くこと
- 日時変更（`ajax_schedule_reservation` の変更ケース）で旧枠の残席が戻り、新枠の残席が減ること
- キャンセルリンク発行・キャンセルページのフローはコードを変更しておらず、影響がないこと

### 4-2. 既存のプレミアム予約（通常の有料予約）フローへの影響

- ホールド作成（`HoldService::create()`）の空席チェックが `KKPAY_Capacity_Service` 経由になった後も、正常系は従来通りに動くこと
- 決済確定（`create_from_hold()`）で `seating_preference = 'Bar'` が保存されること
- 既存の AJAX アクション（`kkpay_create_hold`・`kkpay_confirm_reservation` など）のシグネチャが変わっていないこと

### 4-3. キャンセル処理への影響

- `KKPAY_Cancellation_Service::cancel()` のキャンセルレコード（`kkpay_cancellations`）への書き込みが維持されていること
- 返金なしポリシー（`refund_status = 'none'`）が変わっていないこと
- イベントログ INSERT が追加されたが、キャンセルの可否判定・レコード更新・メール送信のロジックが変わっていないこと

### 4-4. 当日予約プラグインへの影響なし

- `kichikichi-reservation-system` プラグインのコードに変更を加えていないこと
- 今回の変更が当日予約の既存 UI から呼ばれていないこと（PR 4 以降のスコープ）

### 4-5. 管理画面の CSV 出力・一覧表示

- `予約種別`・`席種` 列が追加されても、既存列の並びが崩れていないこと
- 旧レコード（`reservation_type = NULL` または `seating_preference = NULL` のもの）の表示が空白になり、エラーにならないこと（`?? ''` による null 合体の確認）

---

## 5. 確認スクリプト（tools/kkpay-step3-check.php）

### 5-1. スクリプトのスコープ

- 読み取り専用（DB 書き込みなし）であること
- クラスの存在確認とメソッドの存在確認にとどまり、実際の DB データを変更しないこと

### 5-2. 検証項目の網羅性

- Step 3 で新設したクラス・メソッドを検証していること
- Step 1・Step 2 の前提確認（テーブル存在確認）を含んでいること
- `step1-check`・`step2-check` との重複が必要最小限であること
- 読み取り専用スクリプトのため、`HoldService::create()` や `CancellationService::cancel()` など既存メソッド内部の差分挙動までは検証対象外であること

---

## チェックリスト要約

| 番号 | 観点 | 重要度 |
|------|------|--------|
| 2-1 | `create_from_hold()` に `seating_preference = 'Bar'` が含まれているか | **Critical** |
| 2-5 | トランザクション外のイベントログ（fire-and-forget）が意図的であることを明示しているか | **High** |
| 3-2 | `kkpay_reservation_events` テーブル未作成時にブロッキング障害が起きる前提が明示されているか | **High** |
| 2-3 | 日時確定・日時変更がトランザクション内で `FOR UPDATE` を含む空席チェックを通っているか | **High** |
| 2-4 | 日時変更時に自己除外ロジックが `sum_active_people_for_slot_and_seat_excluding_id()` で正しく機能するか | **High** |
| 2-6 | 空席チェックのエラーコードが呼び出し元で一律変換される場合に元コードを診断可能な形で残しているか | **Medium** |
| 2-7 | イベントペイロードに日時変更の `from` / `to` が含まれているか | **Medium** |
| 2-8 | `get_remaining_capacity()` が `kkpay_slot_capacities` を参照し `enabled` を確認しているか | **Medium** |
| 4-1 | 既存スペシャルプレミアム予約フローが壊れていないか | **Medium** |
| 4-5 | NULL カラムを持つ旧レコードが管理画面でエラーにならないか | **Medium** |
| 3-4 | `require_once` 読み込み順に `CapacityService` が各 Service より前に来ているか | **Medium** |
| 1-4 | コーディング規約（ABSPATH・`array()`・インデント）が守られているか | **Medium** |
| 2-5 | `reservation_cancelled` イベントが `COMMIT` より前に INSERT されているか | **Medium** |
| 4-3 | `kkpay_cancellations` への書き込みと返金なしポリシーが維持されているか | **Medium** |
