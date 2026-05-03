# ディレクトリ構造

## 全体ツリー

```
KichiKichi-Paid-Reservation/
│
├── early-reservation-system.php        エントリポイント（定数・require_once・WordPress フック登録）
│
├── doc/                                チーム向けドキュメント（このディレクトリ）
│   ├── 00_overview.md
│   ├── 01_directory_structure.md       ← このファイル
│   ├── 02_layer_controllers.md
│   ├── 03_layer_validators.md
│   ├── 04_layer_services.md
│   ├── 05_layer_repositories.md
│   ├── 06_layer_infrastructure.md
│   ├── 07_data_flow.md
│   ├── 08_database_schema.md
│   ├── 09_stripe_integration.md
│   ├── 10_how_to_add_feature.md
│   └── 11_coding_conventions.md
│
├── includes/
│   │
│   ├── Infrastructure/                 外部サービスとの通信を抽象化する層
│   │   └── class-kkpay-stripe-client.php
│   │
│   ├── Repositories/                   DB 通信層（$wpdb のみを使う）
│   │   ├── class-kkpay-calendar-repository.php
│   │   ├── class-kkpay-cancellation-repository.php
│   │   ├── class-kkpay-hold-repository.php
│   │   └── class-kkpay-reservation-repository.php
│   │
│   ├── Services/                       ビジネスロジック層
│   │   ├── class-kkpay-calendar-service.php
│   │   ├── class-kkpay-cancellation-service.php
│   │   ├── class-kkpay-email-service.php
│   │   ├── class-kkpay-hold-service.php
│   │   ├── class-kkpay-payment-service.php
│   │   └── class-kkpay-reservation-service.php
│   │
│   ├── Validators/                     バリデーション層（$_POST の入口）
│   │   ├── class-kkpay-cancellation-validator.php
│   │   ├── class-kkpay-hold-validator.php
│   │   ├── class-kkpay-payment-validator.php
│   │   └── class-kkpay-reservation-validator.php
│   │
│   ├── Controllers/                    リクエスト受付・レスポンス返却層
│   │   ├── class-kkpay-admin-controller.php
│   │   ├── class-kkpay-cancellation-controller.php
│   │   ├── class-kkpay-hold-controller.php
│   │   ├── class-kkpay-payment-controller.php
│   │   └── class-kkpay-reservation-controller.php
│   │
│   ├── class-kkpay-activator.php       DB テーブル作成・Cron スケジュール登録
│   ├── class-kkpay-admin.php           管理画面 UI レンダリング
│   └── class-kkpay-cron.php            WP-Cron：期限切れホールドの定期削除
│
├── templates/                          ショートコードが出力する HTML テンプレート
│   ├── reservation-form.php            予約フォーム（ステップ 1）
│   ├── payment-page.php                決済ページ（ステップ 2）
│   └── my-reservation.php             予約照会・キャンセルページ
│
└── assets/
    ├── css/
    │   ├── kkpay-form.css              フォーム・決済ページのスタイル
    │   └── kkpay-mypage.css            照会・キャンセルページのスタイル
    └── js/
        ├── kkpay-form.js               予約フォーム・決済の JS ロジック
        └── kkpay-mypage.js             照会・キャンセルの JS ロジック
```

---

## includes/ 各サブディレクトリの命名規則

| ディレクトリ | 命名パターン | クラス名パターン |
|------------|-------------|----------------|
| `Controllers/` | `class-kkpay-{機能名}-controller.php` | `KKPAY_{機能名}_Controller` |
| `Validators/` | `class-kkpay-{機能名}-validator.php` | `KKPAY_{機能名}_Validator` |
| `Services/` | `class-kkpay-{機能名}-service.php` | `KKPAY_{機能名}_Service` |
| `Repositories/` | `class-kkpay-{テーブル名}-repository.php` | `KKPAY_{テーブル名}_Repository` |
| `Infrastructure/` | `class-kkpay-{外部サービス名}-client.php` | `KKPAY_{外部サービス名}_Client` |

---

## エントリポイント（early-reservation-system.php）の役割

このファイルだけが WordPress の「グローバル」に触れます。

| 行われること | 詳細 |
|------------|------|
| 定数定義 | `KKPAY_AMOUNT`, `KKPAY_SLOT_LABELS` など |
| ヘルパー関数定義 | `kkpay_msg()` のみ（グローバル関数は最小限） |
| `require_once` | 全クラスファイルの読み込み（依存順に並べること） |
| WordPress フック登録 | `add_action`, `add_shortcode`, `register_activation_hook` など |

**ここにビジネスロジックや SQL は書かない。** 処理は必ず各層のクラスに委譲する。

---

## require_once の読み込み順

依存関係があるため、以下の順序で読み込む必要があります。

```
1. Infrastructure/   （外部への通信。他の層に依存しない）
2. Repositories/     （DB 通信。他の層に依存しない）
3. Services/         （Repositories と Infrastructure に依存）
4. Validators/       （定数のみに依存。Services には依存しない）
5. Controllers/      （Validators, Services, Repositories に依存）
6. class-kkpay-activator.php（Cron に依存）
7. class-kkpay-admin.php   （Repositories に依存）
8. class-kkpay-cron.php
```

---

## 今後ディレクトリが増える場合の方針

### 機能が増えた場合

新しい「機能ドメイン」（例：クーポン機能）を追加するときは、  
既存のディレクトリに新しいファイルを追加する。新しいディレクトリは作らない。

```
✅ 正しい追加方法
includes/
  Controllers/class-kkpay-coupon-controller.php  ← 追加
  Validators/class-kkpay-coupon-validator.php    ← 追加
  Services/class-kkpay-coupon-service.php        ← 追加
  Repositories/class-kkpay-coupon-repository.php ← 追加

❌ やってはいけない追加方法
includes/
  Coupon/                    ← 機能名でディレクトリを切ると層の境界が崩れる
    coupon-handler.php
    coupon-db.php
```

### 外部サービスが増えた場合

別の決済サービス（例：PayPay）を追加する場合は `Infrastructure/` に追加する。

```
includes/
  Infrastructure/
    class-kkpay-stripe-client.php   （既存）
    class-kkpay-paypay-client.php   （追加）
```

### 管理画面の UI が大きくなった場合

管理画面の HTML テンプレートが複雑になった場合は `templates/admin/` を作る。  
`class-kkpay-admin.php` は UI ロジックの調整役に留め、HTML は別ファイルへ。

```
templates/
  reservation-form.php
  payment-page.php
  my-reservation.php
  admin/                      ← 追加
    partials/
      reservations-tab.php
      calendar-tab.php
```
