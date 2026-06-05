<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Variables provided by KKPAY_Admin::render_reservations_tab():
//   $filter_date, $filter_slot, $results
?>

<?php
$csv_url = add_query_arg( array(
    'action'      => 'kkpay_export_csv',
    'filter_date' => $filter_date,
    'filter_slot' => $filter_slot,
    'nonce'       => wp_create_nonce( 'kkpay_export' ),
), admin_url( 'admin-ajax.php' ) );
$payment_status_labels = array(
    'pending'  => '決済待ち',
    'paid'     => '入金済み',
    'refunded' => '返金済み',
);
$reservation_type_labels = array(
    'premium'         => 'プレミアム予約',
    'special_premium' => 'スペシャルプレミアム予約',
    'same_day'        => '当日予約',
);
$seating_preference_labels = array(
    'Bar'   => 'カウンター',
    'Table' => 'テーブル',
);
?>

<form method="get" style="margin:20px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <input type="hidden" name="page" value="kkpay-settings" />
    <label>日付
        <input type="date" name="filter_date" value="<?php echo esc_attr( $filter_date ); ?>" />
    </label>
    <label>スロット
        <select name="filter_slot">
            <option value="">すべて</option>
            <?php foreach ( array_keys( KKPAY_SLOT_TYPES ) as $slot ) : ?>
                <option value="<?php echo esc_attr( $slot ); ?>" <?php selected( $filter_slot, $slot ); ?>>
                    <?php echo esc_html( KKPAY_SLOT_LABELS['ja'][ $slot ] ?? $slot ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="button">絞り込み</button>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=kkpay-settings' ) ); ?>" class="button">リセット</a>
    <a href="<?php echo esc_url( $csv_url ); ?>" class="button button-primary" style="margin-left:auto;">CSV出力</a>
</form>

<?php if ( empty( $results ) ) : ?>
    <p style="margin-top:16px;">予約が見つかりませんでした。</p>
<?php else : ?>

    <?php
    $by_date = array();
    foreach ( $results as $row ) {
        $by_date[ $row->reservation_date ][ $row->time_slot ][] = $row;
    }
    ?>

    <?php foreach ( $by_date as $date => $slots ) : ?>
        <?php
        $date_coming = 0;
        $date_paid   = 0;
        $date_amount = 0;

        foreach ( $slots as $rows ) {
            foreach ( $rows as $row ) {
                if ( $row->payment_status === 'paid' ) {
                    $date_paid   += (int) $row->number_of_people;
                    $date_amount += (int) $row->amount;
                    if ( $row->cancelled_at === null ) {
                        $date_coming += (int) $row->number_of_people;
                    }
                }
            }
        }
        ?>
        <h2 style="margin-top:28px;"><?php echo esc_html( $date ); ?></h2>
        <p>
            <strong>来店予定席数:</strong> <?php echo $date_coming; ?>席 ／
            <strong>決済済み席数:</strong> <?php echo $date_paid; ?>席 ／
            <strong>売上:</strong> $<?php echo esc_html( number_format( $date_amount ) ); ?>
        </p>

        <?php foreach ( $slots as $slot_key => $rows ) : ?>
            <?php
            $slot_label  = KKPAY_SLOT_LABELS['ja'][ $slot_key ] ?? $slot_key;
            $slot_people = 0;
            $slot_amount = 0;
            ?>
            <h3><?php echo esc_html( $slot_label ); ?></h3>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>ID</th><th>予約種別</th><th>席種</th><th>名前</th><th>メール</th><th>席数</th>
                        <th>金額</th><th>決済ステータス</th><th>言語</th>
                        <th>作成日時</th><th>キャンセル日時</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <?php
                        $is_cancelled = $row->cancelled_at !== null;
                        if ( $row->payment_status === 'paid' && ! $is_cancelled ) {
                            $slot_people += (int) $row->number_of_people;
                            $slot_amount += (int) $row->amount;
                        }
                        ?>
                        <tr>
                            <td><?php echo (int) $row->id; ?></td>
                            <td><?php echo esc_html( $reservation_type_labels[ $row->reservation_type ?? '' ] ?? ( $row->reservation_type ?? '―' ) ); ?></td>
                            <td><?php echo esc_html( $seating_preference_labels[ $row->seating_preference ?? '' ] ?? ( $row->seating_preference ?? '―' ) ); ?></td>
                            <td><?php echo esc_html( $row->name ); ?></td>
                            <td><?php echo esc_html( $row->email ); ?></td>
                            <td><?php echo (int) $row->number_of_people; ?>席</td>
                            <td>$<?php echo esc_html( number_format( (int) $row->amount ) ); ?></td>
                            <td><?php echo esc_html( $payment_status_labels[ $row->payment_status ] ?? $row->payment_status ); ?></td>
                            <td><?php echo esc_html( $row->language ); ?></td>
                            <td><?php echo esc_html( $row->created_at ); ?></td>
                            <td><?php echo esc_html( $row->cancelled_at ?? '―' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="text-align:right;">
                <strong>スロット合計:</strong>
                <?php echo $slot_people; ?>席 ／ $<?php echo esc_html( number_format( $slot_amount ) ); ?>
            </p>
        <?php endforeach; ?>
    <?php endforeach; ?>

<?php endif; ?>
