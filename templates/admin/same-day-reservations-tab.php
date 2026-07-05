<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$by_slot = array();
$totals  = array();
$seat_labels = array(
    'Bar'   => 'カウンター',
    'Table' => 'テーブル',
);
$payment_status_labels = array(
    'pending'      => 'デポジット決済待ち',
    'paid'         => 'デポジット決済済み',
    'refunded'     => '外部返金済み',
    'not_required' => 'デポジット不要',
    'none'         => '決済なし',
);
$format_deposit_amount = function ( $row ) {
    $currency = isset( $row->currency ) ? strtoupper( (string) $row->currency ) : '';
    if ( $currency === '' ) {
        $currency = defined( 'KKPAY_SAME_DAY_DEPOSIT_CURRENCY' ) ? strtoupper( (string) KKPAY_SAME_DAY_DEPOSIT_CURRENCY ) : 'USD';
    }

    return $currency . ' ' . number_format( (int) ( $row->amount ?? 0 ) );
};

foreach ( $results as $row ) {
    $slot = $row->time_slot;
    if ( ! isset( $by_slot[ $slot ] ) ) {
        $by_slot[ $slot ] = array();
    }
    if ( ! isset( $totals[ $slot ] ) ) {
        $totals[ $slot ] = array(
            'active'    => 0,
            'cancelled' => 0,
            'Bar'       => 0,
            'Table'     => 0,
        );
    }

    $by_slot[ $slot ][] = $row;

    if ( $row->status === 'active' && $row->cancelled_at === null ) {
        $people = (int) $row->number_of_people;
        $seat   = $row->seating_preference ?: 'Bar';
        $totals[ $slot ]['active'] += $people;
        if ( isset( $totals[ $slot ][ $seat ] ) ) {
            $totals[ $slot ][ $seat ] += $people;
        }
    } elseif ( $row->status === 'cancelled' || $row->cancelled_at !== null ) {
        $totals[ $slot ]['cancelled']++;
    }
}
?>

<form method="get" style="margin:20px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <input type="hidden" name="page" value="kkpay-settings" />
    <input type="hidden" name="tab" value="same_day_reservations" />
    <label>日付
        <input type="date" name="same_day_date" value="<?php echo esc_attr( $filter_date ); ?>" />
    </label>
    <label>スロット
        <select name="same_day_slot">
            <option value="">すべて</option>
            <?php foreach ( array_keys( KKPAY_SLOT_TYPES ) as $slot ) : ?>
                <option value="<?php echo esc_attr( $slot ); ?>" <?php selected( $filter_slot, $slot ); ?>>
                    <?php echo esc_html( KKPAY_SLOT_LABELS['ja'][ $slot ] ?? $slot ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="button">絞り込み</button>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=kkpay-settings&tab=same_day_reservations' ) ); ?>" class="button">今日に戻る</a>
    <label style="margin-left:auto;">
        <input type="checkbox" id="kkpay-same-day-show-cancelled" checked="checked" />
        キャンセル済みを表示
    </label>
</form>

<?php if ( empty( $results ) ) : ?>
    <p style="margin-top:16px;">当日予約は見つかりませんでした。</p>
<?php else : ?>
    <?php foreach ( $by_slot as $slot_key => $rows ) : ?>
        <?php
        $slot_label = KKPAY_SLOT_LABELS['ja'][ $slot_key ] ?? $slot_key;
        $slot_total = $totals[ $slot_key ];
        ?>
        <h2 style="margin-top:28px;"><?php echo esc_html( $slot_label ); ?></h2>
        <p>
            <strong>来店予定:</strong> <?php echo (int) $slot_total['active']; ?>名
            <span style="margin-left:12px;"><strong>カウンター:</strong> <?php echo (int) $slot_total['Bar']; ?>名</span>
            <span style="margin-left:12px;"><strong>テーブル:</strong> <?php echo (int) $slot_total['Table']; ?>名</span>
            <span style="margin-left:12px;"><strong>キャンセル履歴:</strong> <?php echo (int) $slot_total['cancelled']; ?>件</span>
        </p>

        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>ステータス</th>
                    <th>席種</th>
                    <th>名前</th>
                    <th>メール</th>
                    <th>人数</th>
                    <th>デポジット金額</th>
                    <th>決済状況</th>
                    <th>言語</th>
                    <th>作成日時</th>
                    <th>キャンセル日時</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $row ) : ?>
                    <?php
                    $is_cancelled = $row->status === 'cancelled' || $row->cancelled_at !== null;
                    $seat_key     = $row->seating_preference ?: 'Bar';
                    $seat_label   = $seat_labels[ $seat_key ] ?? $seat_key;
                    $payment_key  = $row->payment_status ?? '';
                    $payment_label = $payment_status_labels[ $payment_key ] ?? ( $payment_key ?: '決済状態未設定' );
                    if ( $is_cancelled && $payment_key === 'paid' ) {
                        $payment_label .= '（返金なし）';
                    }
                    ?>
                    <tr data-kkpay-cancelled="<?php echo $is_cancelled ? '1' : '0'; ?>" style="<?php echo $is_cancelled ? 'opacity:.65;' : ''; ?>">
                        <td><?php echo esc_html( $row->status ?: ( $is_cancelled ? 'cancelled' : 'active' ) ); ?></td>
                        <td><?php echo esc_html( $seat_label ); ?></td>
                        <td><?php echo esc_html( $row->name ); ?></td>
                        <td><?php echo esc_html( $row->email ); ?></td>
                        <td><?php echo (int) $row->number_of_people; ?>名</td>
                        <td><?php echo esc_html( $format_deposit_amount( $row ) ); ?></td>
                        <td><?php echo esc_html( $payment_label ); ?></td>
                        <td><?php echo esc_html( $row->language ); ?></td>
                        <td><?php echo esc_html( $row->created_at ); ?></td>
                        <td><?php echo esc_html( $row->cancelled_at ?: '-' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
<?php endif; ?>
