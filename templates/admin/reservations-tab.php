<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Variables provided by KKPAY_Admin::render_reservations_tab():
//   $filter_date, $filter_slot, $results

$csv_url = add_query_arg( array(
    'action'      => 'kkpay_export_csv',
    'filter_date' => $filter_date,
    'filter_slot' => $filter_slot,
    'nonce'       => wp_create_nonce( 'kkpay_export' ),
), admin_url( 'admin-ajax.php' ) );

$reservation_type_labels = array(
    'premium'         => 'プレミアム予約',
    'special_premium' => 'スペシャルプレミアム予約',
    'same_day'        => '当日予約',
);
$seat_labels = array(
    'Bar'   => 'カウンター',
    'Table' => 'テーブル',
);
$status_labels = array(
    'active'    => '来店予定',
    'cancelled' => 'キャンセル済み',
    'voided'    => '無効',
    'no_show'   => '無断キャンセル',
);
$payment_status_labels = array(
    'pending'      => '決済待ち',
    'paid'         => '決済済み',
    'refunded'     => '返金済み',
    'not_required' => '決済不要',
    'none'         => '決済なし',
);
$language_labels = array(
    'en'    => '英語',
    'ja'    => '日本語',
    'ko'    => '韓国語',
    'zh-CN' => '中国語（簡体字）',
    'zh-TW' => '中国語（繁体字）',
);
$is_cancelled_reservation = function ( $row ) {
    $inactive_statuses = array( 'cancelled', 'voided', 'no_show' );
    return $row->cancelled_at !== null || in_array( $row->status ?? '', $inactive_statuses, true );
};

$by_date = array();
if ( ! empty( $results ) ) {
    $slot_order = array_keys( KKPAY_SLOT_TYPES );
    foreach ( $results as $row ) {
        $date = $row->reservation_date ?: '日付未設定';
        $slot = $row->time_slot ?: 'slot_unknown';
        if ( ! isset( $by_date[ $date ] ) ) {
            $by_date[ $date ] = array();
        }
        if ( ! isset( $by_date[ $date ][ $slot ] ) ) {
            $by_date[ $date ][ $slot ] = array();
        }
        $by_date[ $date ][ $slot ][] = $row;
    }
    ksort( $by_date );
    foreach ( $by_date as &$slots ) {
        uksort( $slots, function ( $a, $b ) use ( $slot_order ) {
            $a_index    = array_search( $a, $slot_order, true );
            $b_index    = array_search( $b, $slot_order, true );
            $a_index    = $a_index === false ? PHP_INT_MAX : $a_index;
            $b_index    = $b_index === false ? PHP_INT_MAX : $b_index;
            return $a_index <=> $b_index;
        } );
    }
    unset( $slots );
}
?>

<form method="get" class="kkpay-admin-reservation-filter">
    <input type="hidden" name="page" value="kkpay-settings" />
    <label>
        日付
        <input type="date" name="filter_date" value="<?php echo esc_attr( $filter_date ); ?>" />
    </label>
    <label>
        時間枠
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
    <span class="kkpay-admin-reservation-filter__spacer"></span>
    <a href="<?php echo esc_url( $csv_url ); ?>" class="button button-primary">CSV出力</a>
</form>

<?php if ( empty( $results ) ) : ?>
    <p class="kkpay-reservation-empty">予約が見つかりませんでした。</p>
<?php else : ?>
    <?php foreach ( $by_date as $date => $slots ) : ?>
        <?php
        $date_active_people = 0;
        $date_total_people  = 0;
        $date_paid_people   = 0;
        $date_amount        = 0;

        foreach ( $slots as $rows ) {
            foreach ( $rows as $row ) {
                $is_cancelled = $is_cancelled_reservation( $row );
                $people       = (int) $row->number_of_people;
                $date_total_people += $people;
                if ( ! $is_cancelled ) {
                    $date_active_people += $people;
                }
                if ( $row->payment_status === 'paid' ) {
                    $date_paid_people += $people;
                    $date_amount      += (int) $row->amount;
                }
            }
        }
        ?>
        <section class="kkpay-reservation-date-card">
            <header class="kkpay-reservation-date-card__header">
                <div class="kkpay-reservation-date-card__title">
                    <h2><?php echo esc_html( $date ); ?></h2>
                    <div class="kkpay-reservation-primary-count">
                        <span>来店予定</span>
                        <strong><?php echo (int) $date_active_people; ?>名</strong>
                    </div>
                </div>
                <div class="kkpay-reservation-date-card__summary">
                    <span class="kkpay-reservation-pill">予約合計 <?php echo (int) $date_total_people; ?>名</span>
                    <span class="kkpay-reservation-pill">決済済み <?php echo (int) $date_paid_people; ?>名</span>
                    <span class="kkpay-reservation-pill">決済済み売上 $<?php echo esc_html( number_format( $date_amount ) ); ?></span>
                </div>
            </header>

            <?php foreach ( $slots as $slot_key => $rows ) : ?>
                <?php
                $slot_label         = KKPAY_SLOT_LABELS['ja'][ $slot_key ] ?? $slot_key;
                $slot_active_people = 0;
                $slot_amount        = 0;

                foreach ( $rows as $row ) {
                    $is_cancelled = $is_cancelled_reservation( $row );
                    if ( ! $is_cancelled ) {
                        $slot_active_people += (int) $row->number_of_people;
                    }
                    if ( $row->payment_status === 'paid' ) {
                        $slot_amount += (int) $row->amount;
                    }
                }
                ?>
                <section class="kkpay-reservation-slot-card">
                    <header class="kkpay-reservation-slot-card__header">
                        <div class="kkpay-reservation-slot-card__title">
                            <h3><?php echo esc_html( $slot_label ); ?></h3>
                            <div class="kkpay-reservation-primary-count">
                                <span>来店予定</span>
                                <strong><?php echo (int) $slot_active_people; ?>名</strong>
                            </div>
                        </div>
                        <div>
                            <span class="kkpay-reservation-pill">決済済み売上 $<?php echo esc_html( number_format( $slot_amount ) ); ?></span>
                        </div>
                    </header>

                    <div class="kkpay-reservation-list">
                        <?php foreach ( $rows as $row ) : ?>
                            <?php
                            $is_cancelled   = $is_cancelled_reservation( $row );
                            $type_key       = $row->reservation_type ?? '';
                            $seat_key       = $row->seating_preference ?? '';
                            $status_key     = $row->status ?? '';
                            $payment_key    = $row->payment_status ?? '';
                            $type_label     = $reservation_type_labels[ $type_key ] ?? ( $type_key ?: '種別未設定' );
                            $seat_label     = $seat_labels[ $seat_key ] ?? ( $seat_key ?: '席種未設定' );
                            $status_label   = $status_labels[ $status_key ] ?? ( $status_key ?: '状態未設定' );
                            $payment_label  = $payment_status_labels[ $payment_key ] ?? ( $payment_key ?: '決済状態未設定' );
                            $language_label = $language_labels[ $row->language ] ?? ( $row->language ?: '言語未設定' );
                            ?>
                            <article class="kkpay-reservation-item <?php echo $is_cancelled ? 'is-cancelled' : ''; ?>">
                                <div class="kkpay-reservation-item__main">
                                    <strong><?php echo esc_html( $row->name ); ?></strong>
                                    <div><?php echo esc_html( $row->email ); ?></div>
                                    <div class="kkpay-reservation-badges">
                                        <span class="kkpay-reservation-badge"><?php echo esc_html( $type_label ); ?></span>
                                        <span class="kkpay-reservation-badge"><?php echo esc_html( $seat_label ); ?></span>
                                        <span class="kkpay-reservation-badge <?php echo $is_cancelled ? 'is-cancelled' : ''; ?>"><?php echo esc_html( $status_label ); ?></span>
                                    </div>
                                </div>
                                <div class="kkpay-reservation-meta">
                                    <div><span>人数</span> <?php echo (int) $row->number_of_people; ?>名</div>
                                    <div><span>金額</span> $<?php echo esc_html( number_format( (int) $row->amount ) ); ?></div>
                                    <div><span>決済</span> <?php echo esc_html( $payment_label ); ?></div>
                                </div>
                                <div class="kkpay-reservation-meta">
                                    <div><span>言語</span> <?php echo esc_html( $language_label ); ?></div>
                                    <div><span>作成</span> <?php echo esc_html( $row->created_at ); ?></div>
                                    <div><span>キャンセル</span> <?php echo esc_html( $row->cancelled_at ?: '-' ); ?></div>
                                </div>
                                <div class="kkpay-reservation-meta">
                                    <div><span>ID</span> <?php echo (int) $row->id; ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
