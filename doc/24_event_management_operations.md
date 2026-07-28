# イベント開催回の運用・移行確認

## 管理画面での運用

1. 「イベント予約」タブで新しい開催回を作成し、タイトルと各日の開催時刻・定員を入力する。
2. `draft` の間に内容を確認する。予約が付いた枠は削除できない。
3. Stripe設定と枠を確認して `open` にする。受付中にできる開催回は常に1件だけである。
4. 受付を止める場合は `closed`、履歴として確定する場合は `archived` にする。`archived` は再公開・編集しない。
5. 一覧から開催回を選び、その開催回だけの予約表示・CSV出力を行う。

CSVはUTF-8 BOM付きで、従来列の末尾に `Event ID`、`Event Title`、`Event Period` を追加する。ファイル名は `kkpay_event_{ID}_{開始日}-{終了日}_reservations_{出力日時}.csv` で、タイトルは含めない。氏名・メール・タイトルは数式として実行されないようCSVインジェクション対策を行う。

新規インストール時に固定イベントや固定枠は自動作成されない。最初の開催回は管理画面から作成する。

## PR5適用前の条件

PR1からPR4までを順に適用し、既存予約が「キチキチBIGオムライスイベント」に関連付いていることを確認してからPR5を適用する。適用前にDBバックアップを取得する。以下の `{prefix}` は実環境のWordPressテーブル接頭辞（例: `wp_`）へ置換する。

```sql
SELECT e.id, e.title, e.status, e.unit_amount, e.currency,
       MIN(s.event_date) AS starts_on, MAX(s.event_date) AS ends_on,
       COUNT(DISTINCT s.id) AS slot_count
FROM {prefix}kkpay_events e
LEFT JOIN {prefix}kkpay_event_slots s ON s.event_id = e.id
GROUP BY e.id, e.title, e.status, e.unit_amount, e.currency
ORDER BY e.id;

SELECT COUNT(*) AS open_event_count
FROM {prefix}kkpay_events
WHERE status = 'open';

SELECT s.*
FROM {prefix}kkpay_event_slots s
LEFT JOIN {prefix}kkpay_events e ON e.id = s.event_id
WHERE e.id IS NULL;

SELECT h.*
FROM {prefix}kkpay_event_holds h
LEFT JOIN {prefix}kkpay_event_slots s ON s.id = h.slot_id
WHERE s.id IS NULL;

SELECT r.*
FROM {prefix}kkpay_event_reservations r
LEFT JOIN {prefix}kkpay_event_slots s ON s.id = r.slot_id
WHERE s.id IS NULL;

SELECT event_id, event_date, event_time, COUNT(*) AS duplicate_count
FROM {prefix}kkpay_event_slots
GROUP BY event_id, event_date, event_time
HAVING COUNT(*) > 1;

SELECT COUNT(*) AS unassigned_slot_count
FROM {prefix}kkpay_event_slots
WHERE event_id IS NULL;
```

期待値は、`open_event_count` が0または1、孤児・重複・未関連枠の各クエリが0件であること。既存開催回はタイトルが「キチキチBIGオムライスイベント」、期間が7月11日から7月19日、単価・通貨が50 USDであることも確認する。
