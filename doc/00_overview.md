# プロジェクト概要・アーキテクチャ思想

## このプラグインについて

**キチキチ 決済予約システム（kkpay）** は、WordPress プラグインとして動作する有料レストラン予約システムです。  
既存の「KichiKichi Reservation System」プラグインが提供する営業カレンダーと連携し、Stripe 決済を通じた有料席予約・管理を実現します。

| 項目 | 内容 |
|------|------|
| プラグインスラッグ | `kkpay` |
| 対応言語 | 英語 / 日本語 / 韓国語 / 簡体字中国語 / 繁体字中国語 |
| 決済プロバイダー | Stripe |
| 対応 WordPress | 5.6 以上 |
| 対応 PHP | 7.2 以上 |

---

## 技術スタック

| 役割 | 採用技術 |
|------|---------|
| サーバーサイド | PHP（WordPress プラグイン API） |
| データベース | MySQL（`$wpdb` 経由） |
| 決済 API | Stripe（PaymentIntent + Webhook） |
| フロントエンド | jQuery + Stripe.js v3 |
| メール送信 | `wp_mail()`（WP Mail SMTP 推奨） |
| スケジューリング | WP-Cron（1 分間隔のホールド掃除） |

---

## 外部依存

### 必須
- **KichiKichi Reservation System プラグイン**  
  `{prefix}calendar` テーブルを提供する。このテーブルがないと営業日判定が機能しない。

### 任意
- **WP Mail SMTP**  
  Xserver など SMTP 制限のある環境でのメール到達率を改善する。

---

## アーキテクチャの思想

### なぜ層を分けるか

WordPress プラグインは「動けば OK」で実装すると、すぐに God Class（何でもクラス）が生まれます。  
特に AJAX ハンドラに「入力検証」「ビジネスロジック」「SQL 発行」「メール送信」が全部混在すると、  
以下の問題が起きます。

- **テストが書けない**：DB や Stripe への依存が散在していると単体でロジックを確認できない
- **再利用できない**：同じ残席計算ロジックを別の場所から呼ぼうとすると重複する
- **変更が怖い**：1 ファイルを変えると予測不能な副作用が起きる

そこで本プラグインは **4 層 + Infrastructure** の構造を採用しています。

### 層の一覧

```
WordPress AJAX / REST API
        │
        ▼
┌─────────────────────┐
│    Controllers 層    │  ← リクエスト受付・レスポンス返却のみ
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│    Validators 層    │  ← $_POST のサニタイズ・ルール検証
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│     Services 層     │  ← ビジネスロジック（予約・決済・キャンセル）
└──────┬──────┬───────┘
       │      │
       ▼      ▼
┌──────────┐  ┌───────────────────┐
│Repos 層  │  │ Infrastructure 層 │
│(DB通信)  │  │ (Stripe API通信)  │
└──────────┘  └───────────────────┘
```

### 各層の依存方向

依存は **上から下の一方向のみ** です。

- Controllers → Validators, Services, Repositories
- Services → Repositories, Infrastructure（StripeClient）
- Repositories → なし（`$wpdb` のみ）
- Infrastructure → なし（`wp_remote_request` のみ）

例外として、以下の横断的な共通 Service は他の Service から呼び出せます。

- `KKPAY_Capacity_Service`: 複数の予約種別から使う空席ロックサービスです。予約作成・日時変更など、同一トランザクション内で `kkpay_slot_capacities` の行ロックを共有する必要がある場合に限り呼び出せます。
- `KKPAY_Calendar_Service`: 営業日・営業枠判定を一元化するサービスです。予約種別ごとの Service が、当日の受付可能スロットを判定する場合に限り呼び出せます。

**下の層が上の層を呼ぶことは禁止** です（循環依存の防止）。

---

## 予約の全体フロー（概要）

```
1. ユーザーが日付・スロット・人数・名前・メールを入力して「次へ」
   → kkpay_create_hold（仮予約、5 分間有効）

2. 決済ページで Stripe カード情報を入力
   → kkpay_create_payment_intent（Stripe に PaymentIntent 作成）
   → Stripe.js がカード決済を実行

3. 決済完了後
   → kkpay_confirm_reservation（予約確定 + 確認メール送信）

4. 万が一 confirm が失敗しても
   → Stripe Webhook（payment_intent.succeeded）が確定処理をフォールバック実行

5. キャンセル
   → kkpay_cancel_reservation（返金なしでキャンセル記録 + キャンセルメール）
```

---

## ドキュメント一覧

| ファイル | 内容 |
|---------|------|
| [01_directory_structure.md](01_directory_structure.md) | ディレクトリ構造と各ファイルの役割 |
| [02_layer_controllers.md](02_layer_controllers.md) | Controllers 層の責務・ルール |
| [03_layer_validators.md](03_layer_validators.md) | Validators 層の責務・ルール |
| [04_layer_services.md](04_layer_services.md) | Services 層の責務・ルール |
| [05_layer_repositories.md](05_layer_repositories.md) | Repositories 層の責務・ルール |
| [06_layer_infrastructure.md](06_layer_infrastructure.md) | Infrastructure 層の責務・ルール |
| [07_data_flow.md](07_data_flow.md) | リクエストからレスポンスまでのデータフロー詳細 |
| [08_database_schema.md](08_database_schema.md) | DB テーブル定義・制約・設計意図 |
| [09_stripe_integration.md](09_stripe_integration.md) | Stripe 連携の仕様・フロー・注意点 |
| [10_how_to_add_feature.md](10_how_to_add_feature.md) | 新機能追加時のステップバイステップガイド |
| [11_coding_conventions.md](11_coding_conventions.md) | 命名規則・コーディング規約 |
