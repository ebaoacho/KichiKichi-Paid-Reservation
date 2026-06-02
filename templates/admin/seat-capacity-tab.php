<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Variables provided by KKPAY_Admin::render_seat_capacity_tab():
//   $today, $tz, $saved, $reserved, $slot_keys, $seat_keys, $capacity_days
?>

<div id="kkpay-seat-capacity" style="margin-top:20px;">
    <p>
        各日付・時間帯の予約可能人数を Bar / Table 別に設定します。営業日カレンダー上で営業していない枠は予約不可として扱います。<br>
        設定した人数は、当日予約・プレミアム予約・スペシャルプレミアム予約の共通空席チェックに反映されます。人数を0にした枠は予約不可になります。
    </p>
    <div style="display:flex;gap:8px;align-items:center;margin:16px 0;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:4px;">
            Bar 一括設定:
            <input type="number" id="kkpay-bulk-cap-bar" min="0" max="255"
                   value="<?php echo esc_attr( KKPAY_MAX_CAPACITY ); ?>"
                   style="width:72px;" />
            名
        </label>
        <label style="display:flex;align-items:center;gap:4px;">
            Table 一括設定:
            <input type="number" id="kkpay-bulk-cap-table" min="0" max="255"
                   value="0"
                   style="width:72px;" />
            名
        </label>
        <button type="button" class="button" id="kkpay-apply-bulk-cap">表示中の Bar / Table に適用</button>
        <button type="button" class="button button-primary" id="kkpay-save-cap">保存する</button>
        <span id="kkpay-cap-message" style="margin-left:8px;font-weight:bold;"></span>
    </div>
    <table class="widefat striped" style="table-layout:fixed;">
        <thead>
            <tr>
                <th style="width:105px;">日付</th>
                <th style="width:36px;">曜日</th>
                <?php foreach ( $slot_keys as $slot ) : ?>
                    <th style="font-size:11px;line-height:1.4;">
                        <?php echo esc_html( KKPAY_SLOT_LABELS['ja'][ $slot ] ?? $slot ); ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $days_output = 0;
            $dow_labels  = array( '日', '月', '火', '水', '木', '金', '土' );

            for ( $i = 0; $i <= $capacity_days; $i++ ) :
                $date     = $today->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
                $calendar = KKPAY_Calendar_Repository::find_by_date( $date );

                $open_slots = array();
                if ( $calendar ) {
                    foreach ( KKPAY_SLOT_TYPES as $slot => $type ) {
                        if ( ( $type === 'lunch' && $calendar->lunch ) || ( $type === 'dinner' && $calendar->dinner ) ) {
                            $open_slots[] = $slot;
                        }
                    }
                } else {
                    $open_slots = $slot_keys;
                }

                if ( empty( $open_slots ) ) {
                    continue;
                }
                $days_output++;

                $dow       = (int) ( new DateTimeImmutable( $date, $tz ) )->format( 'w' );
                $dow_label = $dow_labels[ $dow ];
                $dow_style = $dow === 0 ? 'color:#c00;font-weight:bold;' : ( $dow === 6 ? 'color:#00c;font-weight:bold;' : '' );
            ?>
                <tr class="kkpay-cap-row" data-date="<?php echo esc_attr( $date ); ?>">
                    <td><?php echo esc_html( $date ); ?></td>
                    <td style="text-align:center;<?php echo esc_attr( $dow_style ); ?>"><?php echo esc_html( $dow_label ); ?></td>
                    <?php foreach ( $slot_keys as $slot ) : ?>
                        <?php $is_open = in_array( $slot, $open_slots, true ); ?>
                        <td>
                            <?php if ( $is_open ) : ?>
                                <?php foreach ( $seat_keys as $seat ) : ?>
                                    <?php
                                    $default_capacity = $seat === 'Bar' ? KKPAY_MAX_CAPACITY : 0;
                                    $capacity_row     = $saved[ $date ][ $slot ][ $seat ] ?? null;
                                    $capacity         = $capacity_row ? (int) $capacity_row['capacity'] : $default_capacity;
                                    $current          = $reserved[ $date ][ $slot ][ $seat ] ?? 0;
                                    ?>
                                    <label style="display:block;margin-bottom:6px;">
                                        <span style="display:inline-block;width:38px;"><?php echo esc_html( $seat ); ?></span>
                                        <input type="number" class="kkpay-cap-input"
                                               data-slot="<?php echo esc_attr( $slot ); ?>"
                                               data-seat="<?php echo esc_attr( $seat ); ?>"
                                               min="0" max="255"
                                               value="<?php echo esc_attr( $capacity ); ?>"
                                               style="width:64px;" />
                                        名
                                        <span style="display:block;font-size:11px;color:#555;margin-left:42px;">
                                            予約中: <?php echo (int) $current; ?>名
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <span style="color:#bbb;">休業</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
            <?php if ( $days_output === 0 ) : ?>
                <tr>
                    <td colspan="<?php echo count( $slot_keys ) + 2; ?>" style="padding:16px;">
                        今日から2か月後の月末までに営業日が登録されていません。カレンダープラグインを確認してください。
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
