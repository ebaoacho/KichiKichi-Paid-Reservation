<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Variables provided by KKPAY_Admin::render_event_reservations_tab():
//   $event_slots, $event_holds, $event_reservations, $event_slot_map,
//   $event_status,
//   $overbooked_slot_count, $overbooked_reservation_count, $event_hard_close_failures,
//   $event_persist_failures

$csv_url = add_query_arg( array(
    'action' => 'kkpay_event_export_csv',
    'nonce'  => wp_create_nonce( 'kkpay_event_export' ),
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
    'open'     => '受付中',
    'closed'   => '受付前',
    'archived' => '終了',
);
?>

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

<h2 style="margin-top:20px;">イベント予約 受付ステータス</h2>
<p>現在の状態: <strong id="kkpay-event-status-label"><?php echo esc_html( $event_status_labels[ $event_status ] ?? $event_status ); ?></strong></p>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
    <button id="kkpay-event-start-btn" class="button button-primary" <?php disabled( in_array( $event_status, array( 'open', 'archived' ), true ) ); ?>>受付を開始する</button>
    <button id="kkpay-event-end-btn" class="button" style="border-color:#c8102e;color:#c8102e;" <?php disabled( $event_status, 'archived' ); ?>>イベントを終了する</button>
</div>
<p style="margin:0 0 4px;color:#c8102e;font-size:12.5px;">※「イベントを終了する」は取り消せません。一度終了すると、二度と受付を再開できなくなります。</p>
<p style="margin:0 0 20px;color:#666;font-size:12.5px;">※終了操作の直前に決済処理中だったお客様がいた場合、システムが決済成立を確認できた分はそのまま予約として確定します（決済を後からキャンセルすることはできません）。終了操作の直後は、下記の確定予約一覧に新しい予約が追加されていないか一度ご確認ください。</p>

<div id="kkpay-event-action-message" class="notice" style="display:none;margin:12px 0;"></div>

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
                            <button class="button kkpay-event-cancel-btn" data-id="<?php echo (int) $row->id; ?>">キャンセル（返金なし）</button>
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
