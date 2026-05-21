<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Variables provided by KKPAY_Admin::render_seat_capacity_tab():
//   $today (DateTimeImmutable), $tz (DateTimeZone), $saved (array), $reserved (array), $slot_keys (array)
?>

<div id="kkpay-seat-capacity" style="margin-top:20px;">
    <p>
        各営業日・時間帯の予約可能席数を設定します。カレンダープラグインで営業日に設定されている日程のみ表示されます。<br>
        設定した席数はホールド作成時・予約確定時のチェックに即時反映されます。
    </p>
    <div style="display:flex;gap:8px;align-items:center;margin:16px 0;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:4px;">
            一括設定する席数:
            <input type="number" id="kkpay-bulk-cap-val" min="0" max="255"
                   value="<?php echo esc_attr( KKPAY_MAX_CAPACITY ); ?>"
                   style="width:72px;" />
            席
        </label>
        <button type="button" class="button" id="kkpay-apply-bulk-cap">表示中の全スロットに適用</button>
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
                        <?php echo esc_html( $slot ); ?><br>
                        <span style="font-weight:normal;color:#555;">
                            <?php echo esc_html( KKPAY_SLOT_LABELS['ja'][ $slot ] ?? $slot ); ?>
                        </span>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $days_output = 0;
            $dow_labels  = array( '日', '月', '火', '水', '木', '金', '土' );

            for ( $i = 0; $i <= 90; $i++ ) :
                $date     = $today->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
                $calendar = KKPAY_Calendar_Repository::find_by_date( $date );

                $open_slots = array();
                if ( $calendar ) {
                    foreach ( KKPAY_SLOT_TYPES as $slot => $type ) {
                        if ( ( $type === 'lunch' && $calendar->lunch ) || ( $type === 'dinner' && $calendar->dinner ) ) {
                            $open_slots[] = $slot;
                        }
                    }
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
                        <?php
                        $is_open  = in_array( $slot, $open_slots, true );
                        $capacity = $saved[ $date ][ $slot ] ?? KKPAY_MAX_CAPACITY;
                        ?>
                        <td>
                            <?php if ( $is_open ) : ?>
                                <?php $current = $reserved[ $date ][ $slot ] ?? 0; ?>
                                <input type="number" class="kkpay-cap-input"
                                       data-slot="<?php echo esc_attr( $slot ); ?>"
                                       min="0" max="255"
                                       value="<?php echo esc_attr( $capacity ); ?>"
                                       style="width:64px;" /> 席<br>
                                <span style="font-size:11px;color:#555;">予約中: <?php echo $current; ?>席</span>
                            <?php else : ?>
                                <span style="color:#bbb;">定休</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
            <?php if ( $days_output === 0 ) : ?>
                <tr>
                    <td colspan="<?php echo count( $slot_keys ) + 2; ?>" style="padding:16px;">
                        今後90日間に営業日が登録されていません。カレンダープラグインを確認してください。
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
