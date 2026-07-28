<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Variables provided by KKPAY_Admin::render_event_reservations_tab():
//   $events, $event, $event_slots, $event_holds, $event_reservations, $event_slot_map,
//   $event_status,
//   $overbooked_slot_count, $overbooked_reservation_count, $event_hard_close_failures,
//   $event_persist_failures

$csv_url = add_query_arg( array(
    'action' => 'kkpay_event_export_csv',
    'nonce'  => wp_create_nonce( 'kkpay_event_export' ),
    'event_id' => $event_id,
), admin_url( 'admin-ajax.php' ) );

$reservation_status_labels = array(
    'CONFIRMED' => '確定',
    'CANCELED'  => 'キャンセル済み',
    'REFUNDED'  => '返金済み',
);
$hold_status_labels = array(
    'HELD'            => '仮押さえ中',
    'PENDING_PAYMENT' => '決済待ち',
    'CONFIRMED'       => '確定済み',
    'EXPIRED'         => '期限切れ',
    'CANCELED'        => 'キャンセル済み',
);
$event_status_labels = array(
    'draft'    => '下書き',
    'open'     => '受付中',
    'closed'   => '受付停止',
    'archived' => '終了',
);
$format_event_date = static function ( $date ) {
    $value = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'Asia/Tokyo' ) );
    return $value ? $value->format( 'Y年n月j日' ) : $date;
};
?>

<div style="display:grid;grid-template-columns:minmax(480px,2fr) minmax(280px,1fr);gap:20px;margin-top:20px;align-items:start;">
    <section>
        <h2 style="margin-top:0;">イベント開催履歴</h2>
        <table class="wp-list-table widefat striped">
            <thead><tr><th>タイトル</th><th>開催期間</th><th>状態</th><th>確定人数</th><th>キャンセル</th><th>売上</th></tr></thead>
            <tbody>
            <?php foreach ( $events as $history_event ) : ?>
                <?php
                $detail_url = add_query_arg( array(
                    'page'     => 'kkpay-settings',
                    'tab'      => 'event_reservations',
                    'event_id' => (int) $history_event->id,
                ), admin_url( 'admin.php' ) );
                $period = $history_event->starts_on
                    ? $format_event_date( $history_event->starts_on ) . ( $history_event->ends_on !== $history_event->starts_on ? ' ～ ' . $format_event_date( $history_event->ends_on ) : '' )
                    : '未設定';
                ?>
                <tr<?php echo (int) $history_event->id === $event_id ? ' style="box-shadow:inset 4px 0 #2271b1;"' : ''; ?>>
                    <td><a href="<?php echo esc_url( $detail_url ); ?>"><strong><?php echo esc_html( $history_event->title ); ?></strong></a></td>
                    <td><?php echo esc_html( $period ); ?></td>
                    <td><?php echo esc_html( $event_status_labels[ $history_event->status ] ?? $history_event->status ); ?></td>
                    <td><?php echo (int) $history_event->confirmed_guests; ?>名</td>
                    <td><?php echo (int) $history_event->cancelled_count; ?>件</td>
                    <td>USD <?php echo esc_html( number_format( (int) $history_event->paid_amount ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section style="background:#fff;border:1px solid #c3c4c7;padding:16px;">
        <h2 style="margin-top:0;">新しいイベント</h2>
        <label for="kkpay-event-new-title"><strong>タイトル</strong></label>
        <input id="kkpay-event-new-title" type="text" maxlength="200" class="widefat" style="margin:6px 0 12px;">
        <label for="kkpay-event-new-date"><strong>最初の開催日</strong></label>
        <input id="kkpay-event-new-date" type="date" class="widefat" style="margin:6px 0 12px;">
        <button id="kkpay-event-create-btn" class="button button-primary">下書きを作成</button>
        <p class="description">11:00・12:30・14:00（各8名）の枠を作成します。料金はUSD <?php echo (int) KKPAY_EVENT_AMOUNT; ?>／名で固定です。</p>
    </section>
</div>

<div id="kkpay-event-action-message" class="notice" style="display:none;margin:12px 0;"></div>

<?php if ( $event ) : ?>
    <hr style="margin:28px 0;">
    <h2>イベント詳細: <?php echo esc_html( $event->title ); ?></h2>
    <p>Event ID: <?php echo (int) $event->id; ?> ／ 料金: USD <?php echo (int) $event->unit_amount; ?>／名 ／ 状態: <strong><?php echo esc_html( $event_status_labels[ $event_status ] ?? $event_status ); ?></strong></p>

    <?php if ( $event_status === 'draft' ) : ?>
        <div id="kkpay-event-draft-editor" data-event-id="<?php echo (int) $event->id; ?>">
            <p><label for="kkpay-event-title"><strong>タイトル</strong></label><br>
            <input id="kkpay-event-title" type="text" maxlength="200" class="regular-text" value="<?php echo esc_attr( $event->title ); ?>"></p>
            <div style="display:flex;gap:8px;align-items:end;margin:16px 0;">
                <label><strong>開催日を追加</strong><br><input id="kkpay-event-add-date" type="date"></label>
                <button id="kkpay-event-add-date-btn" class="button">11:00・12:30・14:00の枠を追加</button>
            </div>
            <table class="wp-list-table widefat striped" id="kkpay-event-slot-editor">
                <thead><tr><th>開催日</th><th>時間</th><th>定員</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ( $event_slots as $slot ) : ?>
                    <tr data-slot-id="<?php echo (int) $slot->id; ?>">
                        <td><input class="kkpay-event-slot-date" type="date" value="<?php echo esc_attr( $slot->event_date ); ?>"></td>
                        <td><input class="kkpay-event-slot-time" type="time" value="<?php echo esc_attr( substr( $slot->event_time, 0, 5 ) ); ?>"></td>
                        <td><input class="kkpay-event-slot-capacity" type="number" min="1" max="<?php echo (int) KKPAY_EVENT_MAX_PEOPLE; ?>" value="<?php echo (int) $slot->capacity; ?>"> 名</td>
                        <td><button class="button-link-delete kkpay-event-remove-slot">削除</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><button id="kkpay-event-save-btn" class="button button-primary">下書きを保存</button></p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ( $overbooked_slot_count > 0 || $overbooked_reservation_count > 0 ) : ?>
    <div class="notice notice-error" style="padding:12px 16px;margin:16px 0;">
        <p style="margin:0;"><strong>⚠ 定員超過が検出されています。</strong>
        残席がマイナスの枠: <?php echo (int) $overbooked_slot_count; ?> 件／要確認の確定予約: <?php echo (int) $overbooked_reservation_count; ?> 件。
        決済は完了済みのため自動キャンセルはしていません。下記の一覧で赤字表示された箇所をご確認のうえ、座席調整または返金など個別対応をご検討ください。</p>
    </div>
<?php endif; ?>

<?php if ( ! empty( $event_hard_close_failures ) ) : ?>
    <div class="notice notice-warning" style="padding:12px 16px;margin:16px 0;">
        <p style="margin:0 0 8px;"><strong>⚠ 直近のハードクローズで、完了できなかった処理があります。</strong>
        DBまたはStripeの状態をご確認のうえ、必要に応じて手動で対応してください。</p>
        <ul style="margin:0;padding-left:20px;">
            <?php foreach ( $event_hard_close_failures as $failure ) : ?>
                <li>
                    <?php echo esc_html( $failure['payment_intent_id'] ?: 'hold_token: ' . $failure['hold_token'] ); ?>
                    — <?php echo esc_html( $failure['message'] ); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ( ! empty( $event_persist_failures ) ) : ?>
    <div class="notice notice-warning" style="padding:12px 16px;margin:16px 0;">
        <p style="margin:0 0 8px;"><strong>⚠ PaymentIntentの発行事実をDBに記録できなかった項目があります。</strong>
        決済自体は成立している可能性がありますが、ホールド一覧にPaymentIntentが表示されず追跡しづらくなっています。
        該当のhold_token/PaymentIntentをStripeダッシュボードで確認してください。</p>
        <ul style="margin:0;padding-left:20px;">
            <?php foreach ( $event_persist_failures as $failure ) : ?>
                <li>
                    <?php echo esc_html( $failure['recorded_at'] ); ?> —
                    hold_token: <?php echo esc_html( $failure['hold_token'] ); ?> —
                    <?php echo esc_html( $failure['payment_intent_id'] ); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ( $event ) : ?>
<h2 style="margin-top:20px;">イベント予約 受付ステータス</h2>
<p>現在の状態: <strong id="kkpay-event-status-label"><?php echo esc_html( $event_status_labels[ $event_status ] ?? $event_status ); ?></strong></p>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
    <button id="kkpay-event-start-btn" class="button button-primary" <?php disabled( in_array( $event_status, array( 'open', 'archived' ), true ) ); ?>>受付を開始する</button>
    <button id="kkpay-event-end-btn" class="button" style="border-color:#c8102e;color:#c8102e;" <?php disabled( ! in_array( $event_status, array( 'open', 'closed' ), true ), true ); ?>>イベントを終了する</button>
</div>
<p style="margin:0 0 4px;color:#c8102e;font-size:12.5px;">※「イベントを終了する」は取り消せません。一度終了すると、二度と受付を再開できなくなります。</p>
<p style="margin:0 0 20px;color:#666;font-size:12.5px;">※終了操作の直前に決済処理中だったお客様がいた場合、システムが決済成立を確認できた分はそのまま予約として確定します（決済を後からキャンセルすることはできません）。終了操作の直後は、下記の確定予約一覧に新しい予約が追加されていないか一度ご確認ください。</p>

<?php if ( $event_status !== 'draft' ) : ?>
<h2>枠ごとの残席状況</h2>
<table class="wp-list-table widefat striped" style="margin-bottom:24px;">
    <thead>
        <tr>
            <th>日付</th>
            <th>時間</th>
            <th>定員</th>
            <th>仮押さえ中</th>
            <th>確定</th>
            <th>残席</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $event_slots as $slot ) : ?>
            <?php
            $remaining     = (int) $slot->capacity - (int) $slot->held_count - (int) $slot->confirmed_count;
            $is_over_slot  = $remaining < 0;
            ?>
            <tr>
                <td><?php echo esc_html( $slot->event_date ); ?></td>
                <td><?php echo esc_html( $slot->event_time ); ?></td>
                <td><?php echo (int) $slot->capacity; ?></td>
                <td><?php echo (int) $slot->held_count; ?></td>
                <td><?php echo (int) $slot->confirmed_count; ?></td>
                <td<?php echo $is_over_slot ? ' style="color:#c8102e;font-weight:700;"' : ''; ?>>
                    <?php echo (int) $remaining; ?><?php echo $is_over_slot ? '（超過）' : ''; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2>確定予約一覧</h2>
<div style="margin-bottom:12px;">
    <a href="<?php echo esc_url( $csv_url ); ?>" class="button">CSV出力</a>
</div>

<?php if ( empty( $event_reservations ) ) : ?>
    <p>確定予約はまだありません。</p>
<?php else : ?>
    <table class="wp-list-table widefat striped" style="margin-bottom:24px;">
        <thead>
            <tr>
                <th>予約コード</th>
                <th>ステータス</th>
                <th>決済状態</th>
                <th>名前</th>
                <th>メール</th>
                <th>日付</th>
                <th>時間</th>
                <th>人数</th>
                <th>金額</th>
                <th>PaymentIntent</th>
                <th>確定経路</th>
                <th>超過</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $event_reservations as $row ) : ?>
                <?php $slot = $event_slot_map[ $row->slot_id ] ?? null; ?>
                <tr id="kkpay-event-reservation-row-<?php echo (int) $row->id; ?>"<?php echo ! empty( $row->is_overbooked ) ? ' style="background:#fdecea;"' : ''; ?>>
                    <td><?php echo esc_html( $row->reservation_code ); ?></td>
                    <td><?php echo esc_html( $reservation_status_labels[ $row->reservation_status ] ?? $row->reservation_status ); ?></td>
                    <td><?php echo esc_html( $row->payment_status ); ?></td>
                    <td><?php echo esc_html( $row->name ); ?></td>
                    <td><?php echo esc_html( $row->email ); ?></td>
                    <td><?php echo esc_html( $slot ? $slot->event_date : '―' ); ?></td>
                    <td><?php echo esc_html( $slot ? $slot->event_time : '―' ); ?></td>
                    <td><?php echo (int) $row->guests; ?></td>
                    <td>USD <?php echo esc_html( number_format( (int) $row->amount ) ); ?></td>
                    <td style="word-break:break-all;"><?php echo esc_html( $row->payment_intent_id ); ?></td>
                    <td><?php echo esc_html( $row->confirmed_by ); ?></td>
                    <td>
                        <?php if ( ! empty( $row->is_overbooked ) ) : ?>
                            <span style="color:#c8102e;font-weight:700;">⚠ 超過</span>
                        <?php else : ?>
                            ―
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $row->reservation_status === 'CONFIRMED' ) : ?>
                            <button class="button kkpay-event-cancel-btn" data-id="<?php echo (int) $row->id; ?>" <?php disabled( $event_status, 'archived' ); ?>>キャンセル（返金なし）</button>
                        <?php else : ?>
                            ―
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>ホールド一覧</h2>
<?php if ( empty( $event_holds ) ) : ?>
    <p>ホールドはありません。</p>
<?php else : ?>
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>ステータス</th>
                <th>名前</th>
                <th>メール</th>
                <th>日付</th>
                <th>時間</th>
                <th>人数</th>
                <th>期限</th>
                <th>PaymentIntent</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $event_holds as $hold ) : ?>
                <?php $slot = $event_slot_map[ $hold->slot_id ] ?? null; ?>
                <tr>
                    <td><?php echo esc_html( $hold_status_labels[ $hold->status ] ?? $hold->status ); ?></td>
                    <td><?php echo esc_html( $hold->name ); ?></td>
                    <td><?php echo esc_html( $hold->email ); ?></td>
                    <td><?php echo esc_html( $slot ? $slot->event_date : '―' ); ?></td>
                    <td><?php echo esc_html( $slot ? $slot->event_time : '―' ); ?></td>
                    <td><?php echo (int) $hold->guests; ?></td>
                    <td><?php echo esc_html( $hold->expires_at ); ?></td>
                    <td style="word-break:break-all;"><?php echo esc_html( $hold->payment_intent_id ?? '―' ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php endif; ?>
