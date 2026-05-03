# Repositories 層

## 概要

Repositories 層は **データベースへのすべてのアクセスを担当** します。  
このプラグイン内で `$wpdb` を直接使うのはこの層だけです。  
「どのテーブルにどんな SQL を発行するか」だけを知っており、ビジネスルールは一切持ちません。

---

## ファイル一覧

| ファイル | クラス名 | 対応テーブル |
|---------|---------|------------|
| `class-kkpay-hold-repository.php` | `KKPAY_Hold_Repository` | `{prefix}kkpay_holds` |
| `class-kkpay-reservation-repository.php` | `KKPAY_Reservation_Repository` | `{prefix}kkpay_reservations` |
| `class-kkpay-cancellation-repository.php` | `KKPAY_Cancellation_Repository` | `{prefix}kkpay_cancellations` |
| `class-kkpay-calendar-repository.php` | `KKPAY_Calendar_Repository` | `{prefix}calendar`（外部・読み取り専用） |

---

## Repositories 層がやること（責務）

```
1. $wpdb->prepare() を使った安全なクエリ発行
2. INSERT / SELECT / UPDATE / DELETE の実行
3. 結果を stdClass / stdClass[] / bool / int として返す
```

---

## Repositories 層がやってはいけないこと

| NG | 理由 | 代わりに |
|----|------|---------|
| `START TRANSACTION` / `COMMIT` を書く | トランザクション管理は Service 層の責務 | Service で管理する |
| ビジネス判定（「満席か」など）を行う | ロジックは Service の責務 | Service に任せる |
| 他の Repository を呼ぶ | Repository 間の依存は禁止 | Service が複数の Repository を呼ぶ |
| `$wpdb->prepare()` を使わない | SQL インジェクションの危険がある | 必ず `prepare()` を使う |

---

## メソッド命名規則

| 操作 | メソッド名 | 戻り値 |
|------|----------|-------|
| ID で 1 件取得 | `find_by_id( $id )` | `stdClass \| null` |
| 条件で 1 件取得 | `find_by_{カラム名}( $value )` | `stdClass \| null` |
| 条件で複数取得 | `get_{説明}( ...params )` | `stdClass[]` |
| 挿入 | `insert( array $data )` | `int（挿入ID） \| false` |
| 更新 | `update_{何を}( $id, ...values )` | `void` |
| 削除 | `delete_by_{条件}( $value )` | `void` |
| 合計（ロックなし） | `sum_people_for_slot( $date, $slot )` | `int` |
| 合計（FOR UPDATE） | `sum_people_for_slot_with_lock( $date, $slot )` | `int` |

---

## `_with_lock` メソッドについて

`_with_lock` サフィックスがついたメソッドは **`FOR UPDATE` ロック**を使います。

```php
// ❌ トランザクション外で呼ぶと意味がない
$confirmed = KKPAY_Reservation_Repository::sum_people_for_slot_with_lock( $date, $slot );

// ✅ 必ず Service 側でトランザクションを開いた後に呼ぶ
$wpdb->query( 'START TRANSACTION' );
$confirmed = KKPAY_Reservation_Repository::sum_people_for_slot_with_lock( $date, $slot );
$held      = KKPAY_Hold_Repository::sum_people_for_slot_with_lock( $date, $slot );
// ... INSERT ...
$wpdb->query( 'COMMIT' );
```

> `_with_lock` と通常版の使い分け：  
> - **残席表示**（読み取りのみ）→ ロックなし版（`sum_people_for_slot`）  
> - **仮予約作成**（競合防止が必要）→ ロックあり版（`sum_people_for_slot_with_lock`）

---

## 各 Repository のメソッド一覧

### KKPAY_Hold_Repository

| メソッド | 説明 |
|---------|------|
| `find_by_token( $token )` | 有効期限内のホールドを取得 |
| `find_by_token_any( $token )` | 期限切れ含む全ホールドを取得（Webhook 用） |
| `insert( array $data )` | ホールドを挿入 |
| `delete_by_token( $token )` | ホールドを削除（予約確定後に呼ぶ） |
| `delete_expired()` | 期限切れホールドを一括削除（Cron 用） |
| `sum_people_for_slot( $date, $slot )` | 有効ホールド人数の合計（ロックなし） |
| `sum_people_for_slot_with_lock( $date, $slot )` | 有効ホールド人数の合計（FOR UPDATE） |

### KKPAY_Reservation_Repository

| メソッド | 説明 |
|---------|------|
| `find_by_id( $id )` | ID で取得 |
| `find_by_email( $email )` | メールで最新予約を取得（照会用） |
| `find_by_payment_intent( $pi_id )` | PaymentIntent ID で取得（冪等性チェック用） |
| `insert( array $data )` | 予約レコードを挿入 |
| `update_payment_status( $id, $status, $charge_id )` | 決済ステータス更新 |
| `update_cancelled( $id, $cancelled_at, $status )` | キャンセル処理（cancelled_at + status 更新） |
| `sum_people_for_slot( $date, $slot )` | 確定済み人数合計（ロックなし） |
| `sum_people_for_slot_with_lock( $date, $slot )` | 確定済み人数合計（FOR UPDATE） |
| `get_list( $filter_date, $filter_slot )` | 管理画面リスト取得 |
| `get_list_as_array( $filter_date, $filter_slot )` | CSV エクスポート用（ARRAY_A 形式） |

### KKPAY_Cancellation_Repository

| メソッド | 説明 |
|---------|------|
| `insert( array $data )` | キャンセル履歴を挿入 |

### KKPAY_Calendar_Repository（読み取り専用）

| メソッド | 説明 |
|---------|------|
| `find_by_date( $date_str )` | 指定日の営業情報（lunch/dinner フラグ）を取得 |
| `get_range( $from, $to )` | 期間内の営業情報一覧を取得（管理画面カレンダー用） |

---

## $wpdb の安全な使い方

### prepare() の必須化

```php
// ❌ 絶対 NG（SQL インジェクションの危険）
$wpdb->get_row( "SELECT * FROM {$table} WHERE email = '{$email}'" );

// ✅ 正しい
$wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$table} WHERE email = %s LIMIT 1",
    $email
) );
```

### フォーマット指定子

| 値の型 | フォーマット |
|-------|------------|
| 文字列 | `%s` |
| 整数 | `%d` |
| 浮動小数 | `%f` |

### insert() のフォーマット配列

```php
$wpdb->insert(
    $table,
    array(
        'email'  => $email,   // string
        'amount' => 3000,     // int
    ),
    array( '%s', '%d' )       // データと同じ順番で指定する
);
```

---

## テーブル名の取得

ハードコードしません。常に `$wpdb->prefix` を使います。

```php
// ❌ ハードコードは NG
$table = 'wp_kkpay_holds';

// ✅ プレフィックスを動的に取得
private static function table() {
    global $wpdb;
    return $wpdb->prefix . 'kkpay_holds';
}
```

テーブル名を定数化したい場合は `class-kkpay-activator.php` で定義し、Repository で参照してください。

---

## 新しい Repository を追加する手順

1. `includes/Repositories/class-kkpay-{名前}-repository.php` を作成
2. クラス名を `KKPAY_{名前}_Repository` にする
3. `private static function table()` でテーブル名を返す
4. メソッドは上記の命名規則に従う
5. `early-reservation-system.php` の `require_once` 一覧に追加（Services より前）
