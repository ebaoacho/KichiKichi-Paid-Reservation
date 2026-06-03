<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Variables provided by KKPAY_Admin::render_calendar_tab():
//   $today, $tz, $calendar_days, $calendar, $premium_days
?>

<div id="kkpay-calendar-admin" style="margin-top:20px;">
    <p>
        既存の営業日カレンダーを編集します。ランチ・ディナーを OFF にした枠は、当日予約と席数設定で休業枠として扱われます。<br>
        青背景の日は、席数設定で Bar が有効になっているプレミアム予約可能日です。青背景はこの画面では直接変更せず、席数設定タブの Bar 設定から反映します。
    </p>

    <div style="display:flex;gap:8px;align-items:center;margin:16px 0;flex-wrap:wrap;">
        <button type="button" class="button button-primary" id="kkpay-save-calendar">保存する</button>
        <span id="kkpay-calendar-message" style="margin-left:8px;font-weight:bold;"></span>
    </div>

    <table class="widefat striped" style="max-width:920px;">
        <thead>
            <tr>
                <th style="width:130px;">日付</th>
                <th style="width:48px;">曜日</th>
                <th style="width:140px;">ランチ営業</th>
                <th style="width:140px;">ディナー営業</th>
                <th>プレミアム予約可能日</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $dow_labels = array( '日', '月', '火', '水', '木', '金', '土' );
            for ( $i = 0; $i <= $calendar_days; $i++ ) :
                $date       = $today->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
                $day_config = $calendar[ $date ] ?? array( 'lunch' => 0, 'dinner' => 0 );
                $is_premium = ! empty( $premium_days[ $date ] );
                $is_closed  = (int) $day_config['lunch'] === 0 && (int) $day_config['dinner'] === 0;
                $dow        = (int) ( new DateTimeImmutable( $date, $tz ) )->format( 'w' );
                $dow_style  = $dow === 0 ? 'color:#c00;font-weight:bold;' : ( $dow === 6 ? 'color:#00c;font-weight:bold;' : '' );
                $row_style  = $is_premium ? 'background:#dbeafe;' : ( $is_closed ? 'background:#f3f4f6;' : '' );
            ?>
                <tr class="kkpay-calendar-row" data-date="<?php echo esc_attr( $date ); ?>" style="<?php echo esc_attr( $row_style ); ?>">
                    <td><?php echo esc_html( $date ); ?></td>
                    <td style="text-align:center;<?php echo esc_attr( $dow_style ); ?>"><?php echo esc_html( $dow_labels[ $dow ] ); ?></td>
                    <td>
                        <label>
                            <input type="checkbox" class="kkpay-calendar-lunch" <?php checked( (int) $day_config['lunch'], 1 ); ?> />
                            ランチ
                        </label>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" class="kkpay-calendar-dinner" <?php checked( (int) $day_config['dinner'], 1 ); ?> />
                            ディナー
                        </label>
                    </td>
                    <td>
                        <?php if ( $is_premium ) : ?>
                            <span style="font-weight:bold;color:#1d4ed8;">プレミアム予約可能</span>
                        <?php else : ?>
                            <span style="color:#666;">未設定</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>
