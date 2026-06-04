<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Variables provided by KKPAY_Admin::render_seat_capacity_tab():
//   $today, $tz, $saved, $reserved, $slot_keys, $seat_keys, $capacity_days
$seat_labels = array(
    'Bar'   => 'カウンター',
    'Table' => 'テーブル',
);
?>

<div id="kkpay-seat-capacity" class="kkpay-seat-capacity">
    <div class="kkpay-seat-capacity__header">
        <div>
            <h2>席数設定</h2>
            <p>営業日カレンダーで営業中の日時のみ表示します。休業日と休業枠は席数を設定できません。</p>
        </div>
        <div class="kkpay-seat-capacity__bulk">
            <label>
                <span>カウンター</span>
                <input type="number" id="kkpay-bulk-cap-bar" min="0" max="255" value="<?php echo esc_attr( KKPAY_MAX_CAPACITY ); ?>" />
            </label>
            <label>
                <span>テーブル</span>
                <input type="number" id="kkpay-bulk-cap-table" min="0" max="255" value="0" />
            </label>
            <button type="button" class="button" id="kkpay-apply-bulk-cap">表示中の営業枠に適用</button>
            <button type="button" class="button button-primary" id="kkpay-save-cap">保存する</button>
        </div>
    </div>

    <div id="kkpay-cap-message" class="kkpay-seat-capacity__message" aria-live="polite"></div>

    <div class="kkpay-seat-capacity__days">
        <?php
        $days_output = 0;
        $dow_labels  = array( '日', '月', '火', '水', '木', '金', '土' );

        for ( $i = 0; $i <= $capacity_days; $i++ ) :
            $date       = $today->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
            $open_slots = KKPAY_Calendar_Service::get_open_slot_keys_for_date( $date );

            if ( empty( $open_slots ) ) :
                ?>
                <div class="kkpay-cap-row" data-date="<?php echo esc_attr( $date ); ?>" data-closed="1" hidden></div>
                <?php
                continue;
            endif;

            $days_output++;
            $day       = new DateTimeImmutable( $date, $tz );
            $dow       = (int) $day->format( 'w' );
            $dow_label = $dow_labels[ $dow ];
            $dow_class = $dow === 0 ? ' is-sunday' : ( $dow === 6 ? ' is-saturday' : '' );
            ?>
            <section class="kkpay-cap-row kkpay-seat-capacity__day" data-date="<?php echo esc_attr( $date ); ?>">
                <div class="kkpay-seat-capacity__date">
                    <strong><?php echo esc_html( $day->format( 'n/j' ) ); ?></strong>
                    <span class="<?php echo esc_attr( $dow_class ); ?>"><?php echo esc_html( $dow_label ); ?></span>
                    <small><?php echo esc_html( $date ); ?></small>
                    <em class="kkpay-seat-capacity__unsaved">未保存</em>
                </div>
                <div class="kkpay-seat-capacity__slots">
                    <?php foreach ( $open_slots as $slot ) : ?>
                        <div class="kkpay-seat-capacity__slot">
                            <h3><?php echo esc_html( KKPAY_SLOT_LABELS['ja'][ $slot ] ?? $slot ); ?></h3>
                            <div class="kkpay-seat-capacity__seats">
                                <?php foreach ( $seat_keys as $seat ) : ?>
                                    <?php
                                    $default_capacity = $seat === 'Bar' ? KKPAY_MAX_CAPACITY : 0;
                                    $capacity_row     = $saved[ $date ][ $slot ][ $seat ] ?? null;
                                    $capacity         = $capacity_row ? (int) $capacity_row['capacity'] : $default_capacity;
                                    $current          = $reserved[ $date ][ $slot ][ $seat ] ?? 0;
                                    $seat_label       = $seat_labels[ $seat ] ?? $seat;
                                    $is_saved         = $capacity_row ? 1 : 0;
                                    ?>
                                    <label class="kkpay-seat-capacity__seat">
                                        <span><?php echo esc_html( $seat_label ); ?></span>
                                        <input type="number" class="kkpay-cap-input"
                                               data-slot="<?php echo esc_attr( $slot ); ?>"
                                               data-seat="<?php echo esc_attr( $seat ); ?>"
                                               data-saved="<?php echo esc_attr( $is_saved ); ?>"
                                               min="0" max="255"
                                               value="<?php echo esc_attr( $capacity ); ?>" />
                                        <small>予約中: <?php echo (int) $current; ?>名</small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endfor; ?>

        <?php if ( $days_output === 0 ) : ?>
            <div class="kkpay-seat-capacity__empty">
                今日から2か月後の月末までに営業日が登録されていません。先に営業日カレンダーを設定してください。
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.kkpay-seat-capacity {
    margin-top: 20px;
}

.kkpay-seat-capacity__header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    padding: 18px;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 8px;
}

.kkpay-seat-capacity__header h2 {
    margin: 0 0 8px;
    font-size: 20px;
}

.kkpay-seat-capacity__header p {
    margin: 0;
    color: #646970;
}

.kkpay-seat-capacity__bulk {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
}

.kkpay-seat-capacity__bulk label {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    font-weight: 600;
}

.kkpay-seat-capacity__bulk input,
.kkpay-seat-capacity__seat input {
    width: 72px;
}

.kkpay-seat-capacity__message {
    min-height: 20px;
    margin: 12px 0;
    font-weight: 700;
}

.kkpay-seat-capacity__days {
    display: grid;
    gap: 12px;
}

.kkpay-seat-capacity__day {
    display: grid;
    grid-template-columns: 112px minmax(0, 1fr);
    gap: 14px;
    padding: 14px;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 8px;
}

.kkpay-seat-capacity__day.is-unsaved {
    border-color: #b32d2e;
    box-shadow: 0 0 0 1px #b32d2e;
}

.kkpay-seat-capacity__date {
    display: grid;
    align-content: start;
    gap: 4px;
}

.kkpay-seat-capacity__date strong {
    font-size: 24px;
    line-height: 1;
}

.kkpay-seat-capacity__date span {
    font-weight: 700;
}

.kkpay-seat-capacity__unsaved {
    display: none;
    width: fit-content;
    padding: 2px 8px;
    background: #b32d2e;
    border-radius: 999px;
    color: #fff;
    font-size: 11px;
    font-style: normal;
    font-weight: 700;
}

.kkpay-seat-capacity__day.is-unsaved .kkpay-seat-capacity__unsaved {
    display: inline-block;
}

.kkpay-seat-capacity__date small,
.kkpay-seat-capacity__seat small {
    color: #646970;
}

.kkpay-seat-capacity__date .is-sunday {
    color: #b32d2e;
}

.kkpay-seat-capacity__date .is-saturday {
    color: #2271b1;
}

.kkpay-seat-capacity__slots {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px;
}

.kkpay-seat-capacity__slot {
    padding: 12px;
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    border-radius: 8px;
}

.kkpay-seat-capacity__slot h3 {
    margin: 0 0 10px;
    font-size: 14px;
}

.kkpay-seat-capacity__seats {
    display: grid;
    gap: 8px;
}

.kkpay-seat-capacity__seat {
    display: grid;
    grid-template-columns: 44px 78px 1fr;
    gap: 8px;
    align-items: center;
}

.kkpay-seat-capacity__seat span {
    font-weight: 700;
}

.kkpay-seat-capacity__empty {
    padding: 18px;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    color: #646970;
}

@media (max-width: 782px) {
    .kkpay-seat-capacity__header,
    .kkpay-seat-capacity__day {
        grid-template-columns: 1fr;
        display: grid;
    }

    .kkpay-seat-capacity__bulk {
        justify-content: flex-start;
    }
}
</style>
