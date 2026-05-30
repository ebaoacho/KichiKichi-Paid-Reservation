# Step 2 レビュー観点

Step 2（共通空席ロックサービス）のコードレビューで確認すべき観点をまとめたドキュメント。
根拠ドキュメント: `doc/00_overview.md`、`doc/01_directory_structure.md`、`doc/14_same_day_reservation_integration_design.md`、`doc/15_same_day_reservation_current_spec.md`

---

## 1. アーキテクチャ観点（doc/00・doc/01 より）

### 1-1. 層の配置と依存方向
- Service 層は Repository 層・Infrastructure 層にのみ依存し、上位層（Controller・Validator）には依存しないこと
- `KKPAY_Capacity_Service` が依存してよいのは Repository のみ（`KKPAY_Slot_Capacity_Repository`、`KKPAY_Reservation_Repository`、`KKPAY_Hold_Repository`）
- 下位層（Repository）が Service 層を呼び出していないこと（循環依存の禁止）

### 1-2. `require_once` 読み込み順
- `early-reservation-system.php` の読み込み順は `Infrastructure → Repositories → Services → Validators → Controllers` の順を守ること
- 新規 Service ファイルが既存の Services の **前に** 追加されていないこと（依存する Repository がすでに読み込まれていること）

### 1-3. ファイル・クラス命名
- ファイル名: `class-kkpay-{名前}-service.php`（kebab-case）
- クラス名: `KKPAY_{名前}_Service`（Pascal case + アンダースコア）
- Repository メソッド名: `snake_case`

### 1-4. コーディング規約（CLAUDE.md・doc/11 より）
- `if ( ! defined( 'ABSPATH' ) ) { exit; }` がファイル先頭に存在すること
- 配列構文: `array()` を使い `[]` を使わないこと
- インデント: 4 スペース（タブ禁止）
- 返り値契約: 成功 → 値、失敗 → `WP_Error`。`false` や `0` でエラーを表現しないこと
- ファイル末尾に `?>` 閉じタグを置かないこと

---

## 2. 設計仕様観点（doc/14 PR 2 より）

### 2-1. ロック対象が「席数設定行」であること
- `kkpay_slot_capacities` の行を `SELECT ... FOR UPDATE` でロックすること
- ロック対象が `kkpay_reservations` の行になっていないこと
- 予約 0 件の枠でも `kkpay_slot_capacities` に行が存在すれば競合を防止できること

### 2-2. トランザクション境界の明確化
- `FOR UPDATE` ロックは必ずオープン中のトランザクション内で発行されること
- `KKPAY_Capacity_Service::check_available_for_update()` が `START TRANSACTION` を自前で発行するか、または「呼び出し元がトランザクション内で呼ぶこと」という前提条件がコメントで明示されていること
- `FOR UPDATE` がトランザクション外で呼ばれると即時コミットされロックが解放されてしまうため、この契約の明示は必須

### 2-3. 残席計算ロジックの正確性
- 残席計算式: `capacity − confirmed_people − held_people`
- `confirmed_people`: `status = 'active'` かつ `cancelled_at IS NULL` の予約の `number_of_people` 合計
- `held_people`: 有効な hold（`expires_at > NOW()`）の `number_of_people` 合計
- `seating_preference` で切り分けて計算していること（Bar の held は Bar の残席にのみ影響する）
- キャンセル済み（`cancelled_at IS NOT NULL` または `status != 'active'`）が残席計算から除外されていること

### 2-4. hold の seating_preference 対応
- 現在の `kkpay_holds` テーブルには `seating_preference` カラムが存在しないため、hold の集計は席種別を区別できない
- この暫定仕様（例: Table の hold を 0 扱いにする）が意図的な暫定対応として明示されているか
- 将来 `kkpay_holds` に `seating_preference` を追加する際、この集計ロジックを修正しなければならないリスクが残存していないか

### 2-5. イベントログ要件
- PR 2 の実装内容に「予約作成・日時変更・キャンセルをイベントログに残す」が含まれる
- `kkpay_reservation_events` への書き込みが含まれているか、または次 Step に明示的に持ち越す判断がされているか

### 2-6. 空席不足時のエラーレスポンス
- 残席不足のとき `WP_Error` を返し、`false` や `null` を返さないこと
- エラーコード・メッセージが呼び出し元（将来の Controller）で扱いやすいこと

---

## 3. 前ステップとの依存関係（doc/14 PR 1 → PR 2 の繋がり）

### 3-1. `kkpay_reservations.status` カラムへの依存
- `sum_active_people_for_slot_and_seat()` が `status = 'active'` を使っている場合、PR 1 で追加したカラムへの依存が生じる
- PR 1 が適用済みでない環境では SQL エラーになる可能性があるため、依存関係が明示されているか

### 3-2. `kkpay_slot_capacities` テーブルへの依存
- `KKPAY_Slot_Capacity_Repository::find_for_update()` はこのテーブルが存在することを前提とする
- PR 1 の適用なしに Step 2 の機能を動かそうとすると DB エラーになるため、実行前提が README またはコメントに記載されているか

---

## 4. 既存機能への影響確認（doc/15 より）

### 4-1. 既存の通常予約フローへの影響なし
- 今回の差分が既存の `HoldService`・`ReservationService`・`PaymentService` の挙動を変えていないこと
- 新しいサービスを登録するだけで、既存の AJAX アクションが呼び出すフローに手を加えていないこと

### 4-2. 既存の当日予約プラグインへの影響なし
- `kichikichi-reservation-system` プラグインのコードに変更を加えていないこと
- 今回の新規サービスが当日予約の既存 UI から呼ばれていないこと（PR 4 以降のスコープ）

---

## 5. 確認スクリプト（tools/kkpay-step2-check.php）

### 5-1. スクリプトのスコープ
- 読み取り専用（DB 書き込みなし）であること
- クラスの存在確認とメソッドの存在確認にとどまり、実際の DB データを変更しないこと

### 5-2. 検証項目の網羅性
- Step 2 で追加した全クラス・全メソッドを検証していること
- step1-check との重複が必要最小限であること

---

## チェックリスト要約

| 番号 | 観点 | 重要度 |
|------|------|--------|
| 2-2 | FOR UPDATE にトランザクション前提が明示されているか | **Critical** |
| 2-4 | hold の seating_preference 非対応が暫定実装として明示されているか | **High** |
| 3-1 | `status` カラム依存（PR 1 前提）が明示されているか | **High** |
| 2-5 | イベントログ要件が含まれているか、または明示的に持ち越しか | **Medium** |
| 2-3 | キャンセル済みが残席計算から除外されているか | **High** |
| 1-2 | require_once 読み込み順が正しいか | **Medium** |
| 1-4 | コーディング規約（ABSPATH・array()・インデント）が守られているか | **Medium** |
| 4-1 | 既存予約フローに影響を与えていないか | **Medium** |
