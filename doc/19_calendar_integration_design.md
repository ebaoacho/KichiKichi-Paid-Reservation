# カレンダー統合設計書

## 目的

既存当日予約システムに含まれている営業日カレンダーの仕組みを、このプラグインの管理画面と顧客向け画面に統合する。

最終的には、管理者が同じ管理画面で次を設定できるようにする。

- 営業日
- ランチ営業可否
- ディナー営業可否
- 当日予約で使う `Table` / `Bar` の席数
- プレミアム予約を受け付ける日

顧客側では、ショートコードでカレンダーを表示し、通常営業日とプレミアム予約可能日を視覚的に区別できるようにする。

## 現状

### 既存当日予約システム

既存当日予約プラグインは `{prefix}calendar` テーブルを使って営業日を判定している。

主なカラムは次のとおり。

| カラム | 内容 |
| --- | --- |
| `date` | 対象日 |
| `lunch` | ランチ営業可否 |
| `dinner` | ディナー営業可否 |

当日予約では、この営業日カレンダーをもとに、当日予約で表示する時間枠を決める。

### 現在の有料予約プラグイン

現在の有料予約プラグインは、既存 `{prefix}calendar` を `KKPAY_Calendar_Repository` から読み取っている。

ただし、管理画面から営業日カレンダーを編集するUIはまだない。

また、席数設定は Step 8 で `kkpay_slot_capacities` を正本にした。

| 領域 | 現在の正本 |
| --- | --- |
| 営業日・ランチ/ディナー営業可否 | `{prefix}calendar` |
| 日付・時間枠・席種ごとの席数 | `kkpay_slot_capacities` |
| 当日予約 | `kkpay_reservations` |
| プレミアム予約 | `kkpay_reservations` / `kkpay_premium_reservations` |

## 方針

### 短期方針

短期的には、既存 `{prefix}calendar` を営業日カレンダーの読み書き先として使う。

理由:

- 既存当日予約システムとの互換性を維持できる。
- 現場の営業日設定データをすぐに再利用できる。
- Step 9 までの当日予約統合を壊さずに管理UIを追加できる。

### 中期方針

中期的には、`kkpay_calendar_days` のような自前テーブルへ正本を移す。

理由:

- このプラグイン単独で営業日設定を完結できる。
- 旧当日予約プラグイン停止後も外部テーブルへ依存し続ける必要がなくなる。
- 将来的に祝日、臨時休業、特別営業、メモなどを追加しやすい。

### プレミアム予約可能日の扱い

プレミアム予約可能日は、専用フラグを新設するのではなく、当面は `kkpay_slot_capacities` から導出する。

判定:

```text
対象日に seating_preference = Bar の enabled = 1 行が1件以上ある
```

この日を「プレミアム予約可能日」として扱い、顧客向けカレンダーでは青背景で表示する。

理由:

- プレミアム予約は `Bar` 固定で残席を消費する。
- Step 8 で `Bar` / `Table` の席数設定を `kkpay_slot_capacities` に統合済み。
- 管理者が席数設定で `Bar` を有効化した日を、そのままプレミアム予約可能日として説明できる。

## 画面設計

### 管理者向け: 営業日カレンダータブ

管理画面に「営業日カレンダー」タブを追加する。

表示範囲:

- 今日から2か月後の月末まで
- 既存の席数設定タブと同じ期間

日付ごとに表示する情報:

- 日付
- 曜日
- ランチ営業可否
- ディナー営業可否
- プレミアム予約可能状態
- Bar 席数設定への導線
- Table 席数設定への導線

管理者が変更できる項目:

- ランチ営業 ON/OFF
- ディナー営業 ON/OFF

プレミアム予約可能日は、直接 ON/OFF するのではなく、席数設定タブの `Bar` 席数・enabled から反映する。

### 管理者向け: プレミアム予約可能日の見え方

営業日カレンダータブでは、プレミアム予約可能日を青背景で表示する。

色の意味:

| 表示 | 意味 |
| --- | --- |
| 白背景 | 営業日は登録されているが、プレミアム予約受付日は未設定 |
| 青背景 | プレミアム予約可能日 |
| グレー背景 | 休業日または営業枠なし |

青背景になる条件:

```text
kkpay_slot_capacities に対象日の Bar enabled = 1 が存在する
```

### 顧客向け: カレンダーショートコード

顧客向けにカレンダー表示用ショートコードを追加する。

候補:

```text
[kkpay_customer_calendar]
```

表示内容:

- 月表示カレンダー
- 営業日
- 休業日
- プレミアム予約可能日

顧客側の色:

| 表示 | 意味 |
| --- | --- |
| 白背景 | 通常表示 |
| 青背景 | プレミアム予約可能日 |
| グレー背景 | 休業日 |

顧客向けカレンダーでは、管理者向けの細かい設定値は出さない。

表示する情報は次に絞る。

- 日付
- 営業可否
- プレミアム予約可能かどうか

## データ設計

### 短期

短期では既存 `{prefix}calendar` を読み書きする。

読み取り:

```php
KKPAY_Calendar_Repository::get_range( $from, $to )
```

書き込み:

```php
KKPAY_Calendar_Repository::upsert_day( $date, $lunch, $dinner )
```

`upsert_day()` は新規追加する。

### 中期

中期では `kkpay_calendar_days` を追加する。

候補スキーマ:

| カラム | 型 | 内容 |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | 主キー |
| `calendar_date` | DATE | 対象日 |
| `lunch_enabled` | TINYINT(1) | ランチ営業可否 |
| `dinner_enabled` | TINYINT(1) | ディナー営業可否 |
| `admin_note` | TEXT NULL | 管理者メモ |
| `created_at` | DATETIME | 作成日時 |
| `updated_at` | DATETIME | 更新日時 |

インデックス:

```sql
UNIQUE KEY calendar_date (calendar_date)
```

## API設計

### 管理者向け

追加候補:

| AJAX Action | 内容 |
| --- | --- |
| `kkpay_calendar_get_month` | 管理者向けカレンダー表示データ取得 |
| `kkpay_calendar_save_day` | 営業日のランチ/ディナー設定保存 |

`kkpay_calendar_save_day` は `manage_options` 権限を必須にする。

保存時の入力:

```text
date
lunch_enabled
dinner_enabled
nonce
```

### 顧客向け

追加候補:

| AJAX Action | 内容 |
| --- | --- |
| `kkpay_public_calendar_month` | 顧客向けカレンダー表示データ取得 |

顧客向けレスポンス例:

```json
{
  "days": [
    {
      "date": "2026-06-03",
      "open": true,
      "premium_available": true
    }
  ]
}
```

顧客向けAPIでは、席数や内部ステータスを出しすぎない。

## 依存関係

### Repository

追加・拡張候補:

- `KKPAY_Calendar_Repository::upsert_day()`
- `KKPAY_Calendar_Repository::get_range()`
- `KKPAY_Slot_Capacity_Repository::get_by_date_range()`

### Service

追加候補:

- `KKPAY_Calendar_Admin_Service`
- `KKPAY_Customer_Calendar_Service`

ただし、既存の `KKPAY_Calendar_Service` が営業日判定の中心であるため、まずは同クラスに表示用メソッドを追加し、複雑化したら分ける。

## 実装ステップ案

### PR 10: カレンダー統合設計

この設計書を追加する。

含めるもの:

- 管理者向けカレンダーUI方針
- 顧客向けカレンダーショートコード方針
- プレミアム予約可能日の青背景ルール
- 既存 `{prefix}calendar` と将来 `kkpay_calendar_days` の移行方針

含めないもの:

- DB変更
- 管理画面UI実装
- 顧客向けショートコード実装

### PR 11: 管理者向け営業日カレンダータブ

含めるもの:

- 管理画面に「営業日カレンダー」タブを追加
- 既存 `{prefix}calendar` のランチ/ディナー営業可否を表示
- ランチ/ディナー営業可否を保存
- プレミアム予約可能日を青背景表示

含めないもの:

- 顧客向けショートコード
- `kkpay_calendar_days` への移行

PR 11 の保存処理は、既存の席数設定保存 kkpay_save_slot_capacity と同じ管理画面 AJAX パターンに合わせる。
このため、この PR では専用 Service / Validator は新設せず、Controller で nonce・権限確認、JSON デコード、日付形式チェック、トランザクション管理を行う。
営業日カレンダー保存の責務が複雑化した場合は、後続 PR で Service / Validator に分離する。

### PR 12: 顧客向けカレンダーショートコード

含めるもの:

- `[kkpay_customer_calendar]`
- 初版はサーバーサイドの静的描画とし、月移動 AJAX は後続拡張で扱う
- 顧客向けカレンダー表示
- プレミアム予約可能日の青背景
- 休業日のグレー表示

含めないもの:

- 管理画面の大幅変更
- 予約作成フローの変更

### PR 13: カレンダー正本移行

必要になった場合に実施する。

含めるもの:
- `kkpay_calendar_days` テーブル追加
- 既存 `{prefix}calendar` からの移行
- `KKPAY_Calendar_Repository` の参照先切り替え

含めないもの:

- UIの大幅変更

## レビュー観点

- 既存 `{prefix}calendar` の営業日設定を壊さないか。
- 管理者がランチ/ディナー営業可否を直感的に設定できるか。
- プレミアム予約可能日の青背景が `Bar` の有効設定と一致しているか。
- 顧客向けカレンダーに内部情報を出しすぎていないか。
- 休業日とプレミアム予約可能日の色が混同されないか。
- 旧当日予約プラグイン停止前でも安全に併用できるか。

## 未決事項

- 顧客向けカレンダーを表示専用にするか、予約フォームへのリンクを付けるか。
- プレミアム予約可能日を `kkpay_slot_capacities` から導出するだけで十分か、将来的に専用フラグが必要か。
- 管理者向けカレンダーで月送りを何か月先まで許可するか。
- 今日から2か月後の月末を求める日付計算は、現時点では Admin 内ヘルパーと Premium Validator に同等実装がある。後続 PR で利用箇所が増える場合は共通 Service またはヘルパーへ移す。
- 既存 Repository と同様に `ON DUPLICATE KEY UPDATE ... VALUES()` を使っている箇所は、MySQL 8.0.20 以降の非推奨に合わせて後続 PR で row alias 形式への移行を検討する。

## 解決済み事項

- `kkpay_calendar_days` への移行は PR 13 で実施済み。

## PR 13 以降の正本運用

- PR 13 以降、営業日カレンダーの正本は `kkpay_calendar_days` です。旧プラグイン側の `{prefix}calendar` 変更は `kkpay_calendar_days` に同期されないため、営業日編集は KKPAY 管理画面から行います。
