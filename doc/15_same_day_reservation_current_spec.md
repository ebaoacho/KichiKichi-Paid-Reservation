# 既存当日予約システム仕様固定書

## 目的

当日予約統合のPR 0として、既存プラグイン `kichikichi-reservation-system` の現行仕様を固定する。  
この文書は、以降の実装PRで「UI/UXや業務ルールが意図せず変わっていないか」をレビューするための基準である。

このPRではコードを変更しない。  
既存挙動を読み取り、統合後に踏襲するもの・変更するものを明確にする。

## 調査対象

| 対象 | パス |
| --- | --- |
| 既存当日予約プラグイン | `kichikichi-reservation-system` |
| メインファイル | `reservation-system.php` |
| メインクラス | `includes/class-reservation-system.php` |
| 予約フォーム | `templates/reservation-form.php` |
| 予約確認・キャンセル画面 | `templates/confirm-reservation.php` |
| フロントJS | `assets/js/script.js` |
| 確認・キャンセルJS | `assets/js/confirm-reservation.js` |
| お客様向け営業日カレンダーJS | `assets/js/customer-calendar.js` |

## 既存データベース

既存プラグインは、次のテーブルを作成する。

| テーブル | 役割 |
| --- | --- |
| `{prefix}kichikichi_reservation_customer` | 当日予約の顧客情報 |
| `{prefix}kichikichi_reservation_limits` | 時間枠ごとの `Table` / `Bar` 上限 |
| `{prefix}kichikichi_reservation_start` | 当日予約受付開始時刻 |
| `{prefix}calendar` | 営業日カレンダー。昼・夜の営業可否を保持 |

### `{prefix}kichikichi_reservation_customer`

| カラム | 内容 |
| --- | --- |
| `id` | 主キー |
| `email_address` | メールアドレス。先頭191文字にUNIQUE制約 |
| `name` | 名前 |
| `time` | `slot_1` から `slot_6` |
| `seating_preference` | `Table` または `Bar` |
| `number_of_people` | 人数 |
| `language` | 言語 |

現行仕様では、同じメールアドレスで複数の当日予約を作ることはDB制約上できない。

### `{prefix}kichikichi_reservation_limits`

| カラム | 内容 |
| --- | --- |
| `time` | `slot_1` から `slot_6` |
| `table_limit` | テーブル席の上限 |
| `bar_limit` | カウンター席の上限 |

`time` はUNIQUEである。  
当日予約の空席判定は、日付ではなく時間枠と席種別で判定している。

## 日次削除の現行仕様

既存プラグインは `daily_reservation_data_deletion` を登録し、次のテーブルを削除する。

- `{prefix}kichikichi_reservation_customer`
- `{prefix}kichikichi_reservation_limits`
- `{prefix}kichikichi_reservation_start`

スケジュール登録は `strtotime('15:00:00')` を使っている。  
これは、サーバー時刻がUTCである前提では日本時間0時相当のリセットとして扱われる。

統合後は、当日予約データをリセットしない。  
そのため、この日次削除仕様は踏襲しない。

## 時間枠

既存プラグインは `slot_1` から `slot_6` を使う。  
ラベルは言語ごとに定義されている。

| slot | 内容 |
| --- | --- |
| `slot_1` | Arrival 11:40 / Seating 12:00 - 13:00 |
| `slot_2` | Arrival 12:40 / Seating 13:00 - 14:00 |
| `slot_3` | Arrival 16:40 / Seating 17:00 - 18:00 |
| `slot_4` | Arrival 17:40 / Seating 18:00 - 19:00 |
| `slot_5` | Arrival 18:40 / Seating 19:00 - 20:00 |
| `slot_6` | Arrival 19:40 / Seating 20:00 - 21:00 |

統合後も `slot_1` から `slot_6` のキーと意味は維持する。

## 受付開始・受付終了

### 受付開始

管理画面の「予約開始」ボタンで `start_reservation()` が呼ばれ、`kichikichi_reservation_start` に現在時刻が保存される。

予約フォーム表示時は、最新の `start_time` を取得する。

### 受付終了

予約フォームは次の場合に受付不可表示になる。

- `start_time` がない
- `start_time` から3時間を超えている
- 全時間枠が満席

現行の `fully_check()` は、各時間枠について `Table` または `Bar` のどちらかに空きがあれば「まだ満席ではない」と判定する。  
つまり、受付終了扱いになるのは、全時間枠で `Table` と `Bar` の両方が上限に達している場合である。

3時間判定は `10800` 秒で行われている。

統合後も、当日予約の受付開始操作と3時間制限は原則として踏襲する。

## 受付可能な時間枠

予約保存時、現在時刻に応じて選べる時間枠を制限している。

| 現在時刻 | 許可される枠 |
| --- | --- |
| 09:30以上 12:00未満 | `slot_1`, `slot_2` |
| 13:30以上 16:00未満 | `slot_3`, `slot_4`, `slot_5`, `slot_6` |
| 上記以外 | 予約不可 |

この判定はサーバー側の `save_reservation()` で再検証される。  
フロント側でも `get_server_time` を呼び、同様の時間帯制御をしている。

統合後も、サーバー側で同じ制限を必ず再検証する。

## 入力項目

既存当日予約フォームの入力順は次のとおり。

1. 言語
2. 同意チェック
3. 名前
4. メールアドレス
5. メールアドレス確認
6. 人数
7. 席種別
8. 時間枠
9. 最終同意チェック
10. 予約確定ボタン

統合後も、入力順と画面上の流れは原則として維持する。

## 名前・メール

### 名前

フォーム上は英字入力を前提としている。

```html
pattern="[A-Za-z\s]+"
```

統合後も、当日予約の名前入力は英字ベースの既存UXを維持する。

### メール

予約フォームにはメールアドレスと確認用メールアドレスがある。  
既存JS側で一致確認を行う。

確認・キャンセル画面では、メールアドレスを入力して予約を検索する。

## 席種別

当日予約では、お客様が席種別を選択する。

| 値 | 意味 |
| --- | --- |
| `Table` | テーブル席 |
| `Bar` | カウンター席 |

空席判定は、同じ時間枠の同じ席種別だけを対象にする。

```text
現在の予約人数 + 入力人数 <= table_limit または bar_limit
```

統合後も、当日予約では `Table` / `Bar` を選択できるようにする。

一方、プレミアム予約とスペシャルプレミアム予約は `Bar` 固定とする。

## 空席判定

既存の空席判定は次のテーブルを参照する。

- 上限: `{prefix}kichikichi_reservation_limits`
- 現在予約数: `{prefix}kichikichi_reservation_customer`

判定条件:

```text
time = 対象slot
seating_preference = Table または Bar
```

既存仕様では、日付を持たない当日専用テーブルであるため、日付条件はない。

統合後は、`kkpay_reservations` に `reservation_date = 今日` を入れ、日付条件を含めて判定する。

## 予約保存

既存の `save_reservation()` は、次を検証してから `kichikichi_reservation_customer` へ保存する。

- 時間枠キーが有効か
- 現在時刻に対して選択枠が許可されているか
- 席種別ごとの上限を超えていないか

保存項目:

- `name`
- `email_address`
- `time`
- `seating_preference`
- `number_of_people`
- `language`

統合後は、同等の検証を行ったうえで `kkpay_reservations` に保存する。

## 予約確認

確認ページではメールアドレスを入力する。  
`kichikichi_reservation_customer.email_address` に一致する予約を1件取得し、予約内容を表示する。

表示内容:

- 名前
- メールアドレス
- 時間枠
- 席種別
- 人数
- キャンセルポリシー
- キャンセルボタン

統合後も、メールアドレスで予約確認できる導線は維持する。

## キャンセル

既存のキャンセル処理は、メールアドレスをキーに `kichikichi_reservation_customer` からレコードを削除する。

```text
DELETE FROM kichikichi_reservation_customer WHERE email_address = ?
```

統合後は削除しない。  
`kkpay_reservations.status = cancelled` と `cancelled_at` を更新する。

UI上は、既存と同じようにキャンセル完了を表示する。

## 管理画面

既存管理画面には次のタブがある。

- 予約設定
- 予約リスト
- 営業日カレンダー

### 予約設定

時間枠ごとに `Table` / `Bar` の上限を設定する。  
保存時は `kichikichi_reservation_limits` に `REPLACE` する。

### 予約開始

予約設定タブに「予約開始」ボタンがある。  
押下すると `start_reservation()` が呼ばれ、受付開始時刻が保存される。

### 予約リスト

`kichikichi_reservation_customer` を `time ASC, seating_preference ASC, id ASC` で取得する。  
時間枠ごとにグルーピングし、合計人数を表示する。

表示項目:

- 名前
- メール
- 席種別
- 人数
- 時間枠ごとの合計人数

統合後の当日予約管理画面も、この見え方を踏襲する。

### 営業日カレンダー

`calendar` テーブルの `date`, `lunch`, `dinner` を使う。  
お客様向けカレンダーでは `Open` / `Closed` / `Unknown` を返す。

統合後も、営業日カレンダーの見え方は大きく変えない。

## 多言語

既存当日予約は次の言語に対応している。

- `en`
- `ja`
- `ko`
- `zh-CN`
- `zh-TW`

統合後も同じ言語コードを維持する。

## 統合後に踏襲するもの

- 予約フォームの入力順
- 言語選択
- 英字名入力
- メール確認入力
- `Table` / `Bar` の席種別選択
- 人数と席種別に応じて時間枠を絞る挙動
- 受付開始ボタン
- 受付開始から3時間の受付制限
- 昼枠・夜枠の時間帯制御
- 予約確認ページ
- キャンセルページ
- 管理画面の時間枠ごとの予約一覧
- 多言語対応
- 営業日カレンダーの見え方

## 統合後に変更するもの

| 項目 | 既存 | 統合後 |
| --- | --- | --- |
| 予約保存先 | `kichikichi_reservation_customer` | `kkpay_reservations` |
| 予約削除 | 日次削除・キャンセル時DELETE | 削除せず `status` / `cancelled_at` を更新 |
| 空席判定 | 当日予約テーブルのみ | プレミアム予約・スペシャルプレミアム予約も含める |
| 席数設定 | `kichikichi_reservation_limits` | `kkpay_slot_capacities` |
| 予約履歴 | 残らない | 残す |
| プレミアム予約との競合 | 見ない | `Bar` 席として同じ計算に含める |

## PR 0 の完了条件

- 既存当日予約の仕様がこの文書に整理されている。
- 統合後に踏襲するもの・変更するものが明確になっている。
- コード変更を含まない。
- 以降のPRレビューで参照できる基準文書になっている。
