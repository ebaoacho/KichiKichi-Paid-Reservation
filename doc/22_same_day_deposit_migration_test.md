# 当日予約デポジット PR1 マイグレーションテスト

PR1 の DB 変更だけを確認するための手動実行用テスト。

## 対象

- `kkpay_holds.seating_preference` が新規インストールで作成されること。
- `seating_preference` が無い既存スキーマに対して `KKPAY_Activator::activate()` を実行すると、`dbDelta()` 経由でカラムが追加されること。
- 既存行の `seating_preference` が `Bar` で埋まること。

## 実行方法

XAMPP の PHP を使う場合:

```powershell
C:\xampp\php\php.exe tests\migrations\test-kkpay-holds-seating-preference.php C:\xampp\htdocs\kichikichi\wp-load.php
```

`php` に PATH が通っている環境では次でもよい:

```powershell
php tests\migrations\test-kkpay-holds-seating-preference.php C:\xampp\htdocs\kichikichi\wp-load.php
```

## 注意

テストは一時的なテーブル prefix を使い、終了時に削除する。プラグイン本体の既存テーブルは変更しない。
