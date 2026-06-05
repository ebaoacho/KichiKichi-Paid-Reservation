<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Variables provided by KKPAY_Admin::render_premium_reservations_tab():
//   $filter_name, $results  (array of kkpay_premium_reservations rows)

$status_labels = array(
    'expired'            => '期限切れ',
    'link_issued'        => '決済リンク発行済み',
    'paid'               => '入金済み',
    'scheduled'          => '日時確定済み',
    'cancel_link_issued' => 'キャンセルリンク発行済み',
    'cancelled'          => 'キャンセル済み',
);
$payment_status_labels = array(
    'unpaid'   => '未入金',
    'paid'     => '入金済み',
    'refunded' => '返金済み',
    'pending'  => '決済待ち',
);

$csv_url = add_query_arg( array(
    'action'       => 'kkpay_premium_export_csv',
    'premium_name' => $filter_name,
    'nonce'        => wp_create_nonce( 'kkpay_premium_export' ),
), admin_url( 'admin-ajax.php' ) );

$tz              = new DateTimeZone( 'Asia/Tokyo' );
$today           = new DateTimeImmutable( 'today', $tz );
$schedule_min    = $today->format( 'Y-m-d' );
$schedule_max    = KKPAY_Premium_Reservation_Validator::one_month_later( $today )->format( 'Y-m-d' );
?>

<div style="margin:20px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <button id="kkpay-premium-issue-link-btn" class="button button-primary">決済リンク発行</button>
    <a href="<?php echo esc_url( $csv_url ); ?>" class="button" style="margin-left:auto;">CSV出力</a>
</div>

<form method="get" style="margin:12px 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <input type="hidden" name="page" value="kkpay-settings" />
    <input type="hidden" name="tab" value="premium_reservations" />
    <label>名前
        <input type="search" name="premium_name" value="<?php echo esc_attr( $filter_name ); ?>" placeholder="名前で検索" />
    </label>
    <button type="submit" class="button">検索</button>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=kkpay-settings&tab=premium_reservations' ) ); ?>" class="button">リセット</a>
</form>

<div id="kkpay-premium-link-result" style="display:none;margin:12px 0;padding:12px;background:#f0f8ff;border:1px solid #aed6f1;border-radius:4px;">
    <strong>決済リンク:</strong>
    <span id="kkpay-premium-link-url" style="word-break:break-all;"></span>
    <button class="button" style="margin-left:8px;" onclick="navigator.clipboard.writeText(document.getElementById('kkpay-premium-link-url').textContent)">コピー</button>
</div>

<div id="kkpay-premium-action-message" class="notice" style="display:none;margin:12px 0;"></div>

<?php if ( empty( $results ) ) : ?>
    <p style="margin-top:16px;">スペシャルプレミアム予約が見つかりませんでした。</p>
<?php else : ?>

    <table class="wp-list-table widefat striped" style="margin-top:16px;">
        <thead>
            <tr>
                <th>ステータス</th>
                <th>名前</th>
                <th>メール</th>
                <th>言語</th>
                <th>入金状況</th>
                <th>席数</th>
                <th>金額</th>
                <th>予約日</th>
                <th>時間枠</th>
                <th>操作</th>
                <th>キャンセル日時</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $results as $row ) : ?>
                <?php
                $status_label = $status_labels[ $row->status ] ?? esc_html( $row->status );
                $payment_status_label = $payment_status_labels[ $row->payment_status ] ?? $row->payment_status;
                $slot_label   = KKPAY_SLOT_LABELS['ja'][ $row->time_slot ] ?? ( $row->time_slot ?? '―' );
                ?>
                <tr id="kkpay-premium-row-<?php echo (int) $row->id; ?>"<?php if ( $row->status === 'paid' ) : ?> style="background:#fff3cd;"<?php endif; ?>>
                    <td>
                        <?php echo esc_html( $status_label ); ?>
                        <?php if ( $row->status === 'paid' ) : ?>
                            <span style="display:inline-block;margin-left:6px;padding:2px 6px;background:#e67e22;color:#fff;border-radius:3px;font-size:11px;font-weight:bold;">日時未確定</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $row->name ?? '―' ); ?></td>
                    <td><?php echo esc_html( $row->email ?? '―' ); ?></td>
                    <td><?php echo esc_html( $row->language ); ?></td>
                    <td><?php echo esc_html( $payment_status_label ); ?></td>
                    <td><?php echo (int) ( $row->number_of_people ?? 1 ); ?>席</td>
                    <td>USD <?php echo esc_html( number_format( (int) $row->amount ) ); ?></td>
                    <td><?php echo esc_html( $row->reservation_date ?? '―' ); ?></td>
                    <td><?php echo esc_html( $slot_label ); ?></td>
                    <td>
                        <?php if ( in_array( $row->status, array( 'paid', 'scheduled', 'cancel_link_issued' ), true ) ) : ?>
                            <div class="kkpay-schedule-form" data-id="<?php echo (int) $row->id; ?>">
                                <input type="date" class="kkpay-schedule-date" style="width:140px;"
                                       min="<?php echo esc_attr( $schedule_min ); ?>"
                                       max="<?php echo esc_attr( $schedule_max ); ?>"
                                       value="<?php echo esc_attr( $row->reservation_date ?? '' ); ?>" />
                                <select class="kkpay-schedule-slot">
                                    <?php foreach ( array_keys( KKPAY_SLOT_TYPES ) as $slot_key ) : ?>
                                        <option value="<?php echo esc_attr( $slot_key ); ?>" <?php selected( $row->time_slot, $slot_key ); ?>>
                                            <?php echo esc_html( KKPAY_SLOT_LABELS['ja'][ $slot_key ] ?? $slot_key ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button kkpay-schedule-btn" data-id="<?php echo (int) $row->id; ?>">
                                    <?php echo $row->status === 'paid' ? '日時確定' : '日時変更'; ?>
                                </button>
                            </div>
                            <?php if ( $row->status === 'scheduled' ) : ?>
                                <div style="margin-top:6px;">
                                    <button class="button kkpay-issue-cancel-link-btn" data-id="<?php echo (int) $row->id; ?>">キャンセルリンク発行</button>
                                </div>
                            <?php elseif ( $row->status === 'cancel_link_issued' ) : ?>
                                <div style="margin-top:6px;"><span>キャンセルリンク発行済み</span></div>
                            <?php endif; ?>
                        <?php else : ?>
                            ―
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $row->cancelled_at ?? '―' ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

<div id="kkpay-premium-cancel-link-result" style="display:none;margin:12px 0;padding:12px;background:#fff8e1;border:1px solid #f9ca24;border-radius:4px;">
    <strong>キャンセルリンク:</strong>
    <span id="kkpay-premium-cancel-link-url" style="word-break:break-all;"></span>
    <button class="button" style="margin-left:8px;" onclick="navigator.clipboard.writeText(document.getElementById('kkpay-premium-cancel-link-url').textContent)">コピー</button>
</div>
