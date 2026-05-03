# コーディング規約・命名規則

このプロジェクトで統一すべきルールをまとめます。  
既存コードもこの規約に従っています。新しいコードを書く前に必ず読んでください。

---

## PHP コーディングスタイル

### 基本

- インデント：**スペース 4 つ**（タブ禁止）
- 文字コード：**UTF-8**
- 改行コード：**LF**
- PHP タグ：`<?php` のみ（`<?` ショートタグ禁止）
- ファイル末尾：閉じタグ `?>` を書かない

### 中括弧と空白

```php
// ✅ 制御構文の中括弧
if ( $condition ) {
    // 4スペースインデント
}

// ✅ 関数・メソッドの定義
public static function method_name( $arg1, $arg2 ) {
    // ...
}

// ✅ 演算子の前後にスペース
$result = $a + $b;
$array  = array( 'key' => 'value' );

// ✅ 配列のアロー演算子は揃える（長い場合）
array(
    'name'   => $name,
    'email'  => $email,
    'amount' => $amount,
);
```

### 配列の記法

```php
// ✅ array() 記法（WordPress コーディングスタンダードに準拠）
$data = array( 'key' => 'value' );

// ❌ [] 記法は使わない（WordPress 規約外）
$data = [ 'key' => 'value' ];
```

---

## 命名規則

### ファイル名

| 対象 | パターン | 例 |
|------|---------|---|
| クラスファイル | `class-kkpay-{名前}.php` | `class-kkpay-hold-service.php` |
| テンプレートファイル | `{名前}.php` | `reservation-form.php` |
| JS ファイル | `kkpay-{名前}.js` | `kkpay-form.js` |
| CSS ファイル | `kkpay-{名前}.css` | `kkpay-form.css` |

ファイル名はすべて **小文字のケバブケース（`-` 区切り）** です。

### クラス名

| 対象 | パターン | 例 |
|------|---------|---|
| Controllers 層 | `KKPAY_{機能名}_Controller` | `KKPAY_Hold_Controller` |
| Validators 層 | `KKPAY_{機能名}_Validator` | `KKPAY_Hold_Validator` |
| Services 層 | `KKPAY_{機能名}_Service` | `KKPAY_Hold_Service` |
| Repositories 層 | `KKPAY_{テーブル名}_Repository` | `KKPAY_Hold_Repository` |
| Infrastructure 層 | `KKPAY_{サービス名}_Client` | `KKPAY_Stripe_Client` |

クラス名はすべて **アッパーキャメルケース（PascalCase）** に `_` を組み合わせた形式です。

### メソッド名・変数名

| 対象 | パターン | 例 |
|------|---------|---|
| public メソッド | `snake_case` | `create_payment_intent()` |
| private メソッド | `snake_case` | `process_stripe_refund()` |
| ローカル変数 | `snake_case` | `$hold_token`, `$filter_date` |
| 引数 | `snake_case` | `$reservation_id`, `$lang` |

### AJAX アクション名

```
kkpay_{動詞}_{名詞}

例:
  kkpay_create_hold
  kkpay_get_available_slots
  kkpay_confirm_reservation
  kkpay_cancel_reservation
```

### 定数名

```
KKPAY_{大文字スネークケース}

例:
  KKPAY_AMOUNT
  KKPAY_MAX_CAPACITY
  KKPAY_SLOT_TYPES
```

---

## 戻り値の規約

### Service・Repository の戻り値

| 状況 | 戻り値の型 |
|------|----------|
| 成功（単一レコード） | `stdClass` |
| 成功（複数レコード） | `stdClass[]` |
| 成功（ID） | `int` |
| 成功（トークン） | `string` |
| 成功（複合データ） | `array`（連想配列） |
| 失敗（すべて） | `WP_Error` |
| レコードなし | `null`（Repository の `find_*` のみ） |

**`false` や `0` でエラーを表現しない。** 失敗は常に `WP_Error` を返します。

### Controller の判定パターン

```php
// ✅ 統一パターン
$result = KKPAY_{Name}_Service::method( ... );
if ( is_wp_error( $result ) ) {
    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}
// $result を使った処理
wp_send_json_success( array( ... ) );
```

---

## エラーメッセージの書き方

### kkpay_msg() を必ず使う

```php
// ❌ ハードコード
return new WP_Error( 'closed', 'この日は定休日です。' );

// ✅ 多言語対応
return new WP_Error( 'closed', kkpay_msg( 'closed', $lang ) );
```

### 新しいメッセージキーを追加する場合

`early-reservation-system.php` の `KKPAY_MESSAGES` 配列に 5 言語すべての翻訳を追加します。

```php
define( 'KKPAY_MESSAGES', array(
    // ... 既存 ...
    'new_message_key' => array(
        'en'    => 'English message',
        'ja'    => '日本語メッセージ',
        'ko'    => '한국어 메시지',
        'zh-CN' => '简体中文消息',
        'zh-TW' => '繁體中文訊息',
    ),
) );
```

---

## WordPress API の使い方

### グローバル変数

```php
// ✅ 関数の先頭で宣言する
public static function method() {
    global $wpdb;
    // ...
}

// ❌ クラスのプロパティに格納しない
private static $wpdb; // NG
```

### nonce の使い方

| 対象 | アクション名 | 確認方法 |
|------|------------|---------|
| 公開 AJAX | `kkpay_nonce` | `check_ajax_referer( 'kkpay_nonce', 'nonce' )` |
| CSV エクスポート | `kkpay_export` | `check_ajax_referer( 'kkpay_export', 'nonce' )` |

新しいエンドポイントは `kkpay_nonce` を使います。  
別の nonce アクション名が必要な場合はここに追記してください。

---

## セキュリティルール（必須）

### SQL インジェクション対策

```php
// ❌ 絶対 NG
$wpdb->get_row( "SELECT * FROM table WHERE id = {$id}" );

// ✅ 必ず prepare() を使う
$wpdb->get_row( $wpdb->prepare( "SELECT * FROM table WHERE id = %d", $id ) );
```

### XSS 対策（出力エスケープ）

```php
// HTML 属性への出力
echo esc_attr( $value );

// HTML 本文への出力
echo esc_html( $value );

// URL への出力
echo esc_url( $url );

// ✅ 管理画面のテーブルではすべてエスケープする
echo '<td>' . esc_html( $row->name ) . '</td>';
```

### 管理者権限チェック

```php
// 管理者専用エンドポイントでは必ず確認する
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( array( 'message' => 'Unauthorized' ) );
}
```

---

## コメントの書き方

コメントは「なぜそのコードが存在するか」が自明でない場合のみ書きます。  
「何をしているか」は読めばわかるので書きません。

```php
// ❌ 不要なコメント（コード自体が説明している）
// メールを送信する
KKPAY_Email_Service::send_booking_confirmation( $reservation );

// ✅ 有用なコメント（なぜそうするかが自明でない）
// Webhook 到達時点でホールドが期限切れの場合があるため find_by_token_any を使用
$hold = KKPAY_Hold_Repository::find_by_token_any( $hold_token );
```

---

## ファイルの先頭に書くこと

すべての PHP ファイルはこのガードから始まります。

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

WordPress 外から直接ファイルを実行されることを防ぎます。

---

## Git コミットメッセージ

```
{type}: {変更内容の日本語一行説明}

例:
  feat: クーポンコード機能を追加
  fix: キャンセル時の返金判定が正しく動作しない不具合を修正
  refactor: AdminController を分離して Admin クラスを UI のみにする
  docs: Stripe 連携の仕様ドキュメントを追加
  chore: KKPAY_VERSION を 1.0.2 に更新
```

| type | 意味 |
|------|------|
| `feat` | 新機能 |
| `fix` | バグ修正 |
| `refactor` | リファクタリング（動作変更なし） |
| `docs` | ドキュメントのみの変更 |
| `chore` | ビルド・設定変更など |
