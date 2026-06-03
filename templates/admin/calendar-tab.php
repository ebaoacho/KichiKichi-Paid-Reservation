<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$calendar_payload = array();
for ( $i = 0; $i <= $calendar_days; $i++ ) {
    $date       = $today->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
    $day_config = $calendar[ $date ] ?? array( 'lunch' => 0, 'dinner' => 0, 'premium' => 0 );

    $calendar_payload[ $date ] = array(
        'lunch'   => (int) $day_config['lunch'],
        'dinner'  => (int) $day_config['dinner'],
        'premium' => ! empty( $day_config['premium'] ) ? 1 : 0,
    );
}
?>

<div id="kkpay-calendar-admin" class="kkpay-admin-calendar">
    <div class="kkpay-admin-calendar__intro">
        <h2>営業日カレンダー</h2>
        <p>
            営業種別またはプレミアム予約可否を選び、カレンダーの日付をクリックして設定します。
            プレミアム予約可能日は席数設定とは別にこの画面で管理します。
        </p>
    </div>

    <div id="calendar-container" data-calendar="<?php echo esc_attr( wp_json_encode( $calendar_payload ) ); ?>" data-start="<?php echo esc_attr( $today->format( 'Y-m-d' ) ); ?>" data-days="<?php echo esc_attr( $calendar_days ); ?>"></div>

    <div id="calendar-controls-container">
        <div class="calendar-controls">
            <input type="radio" name="schedule-type" id="holiday-checkbox" value="holiday" />
            <label for="holiday-checkbox">定休日</label>

            <input type="radio" name="schedule-type" id="lunch-checkbox" value="lunch" />
            <label for="lunch-checkbox">ランチ営業</label>

            <input type="radio" name="schedule-type" id="dinner-checkbox" value="dinner" />
            <label for="dinner-checkbox">ディナー営業</label>

            <input type="radio" name="schedule-type" id="both-checkbox" value="both" />
            <label for="both-checkbox">両方営業</label>

            <input type="radio" name="schedule-type" id="premium-on-checkbox" value="premium_on" />
            <label for="premium-on-checkbox">プレミアム可</label>

            <input type="radio" name="schedule-type" id="premium-off-checkbox" value="premium_off" />
            <label for="premium-off-checkbox">プレミアム不可</label>
        </div>

        <button type="button" id="update-calendar" class="button button-primary">表示中の月を保存</button>
        <span id="kkpay-calendar-message" class="kkpay-admin-calendar__message"></span>
    </div>
</div>

<style>
    .kkpay-admin-calendar {
        max-width: 980px;
        margin-top: 20px;
        color: #1f2937;
    }

    .kkpay-admin-calendar__intro {
        margin: 0 0 18px;
        padding: 16px 18px;
        border: 1px solid #dcdcde;
        border-radius: 8px;
        background: #fff;
    }

    .kkpay-admin-calendar__intro h2 {
        margin: 0 0 6px;
        font-size: 20px;
        line-height: 1.35;
    }

    .kkpay-admin-calendar__intro p {
        margin: 0;
        color: #4b5563;
    }

    .kkpay-admin-calendar__month-nav {
        display: grid;
        grid-template-columns: 120px 1fr 120px;
        align-items: center;
        gap: 12px;
        margin: 0 0 12px;
    }

    .kkpay-admin-calendar__month-nav h2 {
        margin: 0;
        text-align: center;
        font-size: 22px;
        line-height: 1.35;
    }

    .kkpay-admin-calendar__month-nav .button {
        min-height: 36px;
    }

    .custom-calendar {
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden;
        margin: 0 0 18px;
        border: 1px solid #dcdcde;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    }

    .custom-calendar th {
        padding: 10px 6px;
        background: #f6f7f7;
        border-bottom: 1px solid #dcdcde;
        color: #374151;
        font-size: 13px;
        font-weight: 700;
        text-align: center;
    }

    .custom-calendar td {
        width: 14.28%;
        min-height: 96px;
        padding: 0;
        border-right: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
    }

    .custom-calendar tr td:last-child,
    .custom-calendar tr th:last-child {
        border-right: 0;
    }

    .custom-calendar .calendar-day {
        position: relative;
        height: 96px;
        padding: 10px;
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
    }

    .custom-calendar .calendar-day:hover:not(.is-out-of-range) {
        transform: translateY(-1px);
        box-shadow: inset 0 0 0 2px #1d4ed8;
        filter: brightness(1.02);
    }

    .custom-calendar .calendar-day.is-out-of-range {
        cursor: default;
        opacity: 0.35;
        background: #f9fafb;
    }

    .custom-calendar .holiday {
        background: #fee2e2;
        color: #7f1d1d;
    }

    .custom-calendar .lunch {
        background: #ffedd5;
        color: #7c2d12;
    }

    .custom-calendar .dinner {
        background: #dbeafe;
        color: #1e3a8a;
    }

    .custom-calendar .both {
        background: #dcfce7;
        color: #14532d;
    }

    .custom-calendar .selected {
        box-shadow: inset 0 0 0 3px #111827;
    }

    .custom-calendar .today::after {
        content: "";
        position: absolute;
        inset: 4px;
        border: 2px solid #dc2626;
        border-radius: 6px;
        pointer-events: none;
    }

    .custom-calendar .premium-enabled::before {
        content: "";
        position: absolute;
        inset: 0;
        border-top: 5px solid #2563eb;
        pointer-events: none;
    }

    .kkpay-calendar-day-number {
        display: block;
        margin-bottom: 7px;
        font-size: 17px;
        font-weight: 800;
        line-height: 1;
    }

    .kkpay-calendar-state {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 3px 8px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.72);
        font-size: 11px;
        font-weight: 700;
        line-height: 1.2;
    }

    .kkpay-calendar-premium {
        display: inline-flex;
        margin-top: 7px;
        padding: 3px 8px;
        border-radius: 999px;
        background: #2563eb;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.2;
    }

    .calendar-controls {
        display: grid;
        grid-template-columns: repeat(3, minmax(120px, 1fr));
        gap: 10px;
        width: 100%;
        max-width: 560px;
        margin: 18px auto;
    }

    .calendar-controls input[type="radio"] {
        display: none;
    }

    .calendar-controls label {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 0 12px;
        border: 1px solid #c3c4c7;
        border-radius: 8px;
        background: #fff;
        color: #1f2937;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: border-color 0.12s ease, box-shadow 0.12s ease, transform 0.12s ease;
    }

    .calendar-controls label:hover {
        border-color: #2271b1;
        box-shadow: 0 3px 10px rgba(34, 113, 177, 0.12);
        transform: translateY(-1px);
    }

    .calendar-controls input[type="radio"]:checked + label {
        border-color: #111827;
        box-shadow: inset 0 0 0 2px #111827;
    }

    .calendar-controls input[type="radio"][value="holiday"]:checked + label {
        background: #fee2e2;
    }

    .calendar-controls input[type="radio"][value="lunch"]:checked + label {
        background: #ffedd5;
    }

    .calendar-controls input[type="radio"][value="dinner"]:checked + label {
        background: #dbeafe;
    }

    .calendar-controls input[type="radio"][value="both"]:checked + label {
        background: #dcfce7;
    }

    .calendar-controls input[type="radio"][value="premium_on"]:checked + label {
        background: #dbeafe;
        color: #1e3a8a;
    }

    .calendar-controls input[type="radio"][value="premium_off"]:checked + label {
        background: #f3f4f6;
    }

    #calendar-controls-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 14px 16px 18px;
        border: 1px solid #dcdcde;
        border-radius: 8px;
        background: #fff;
    }

    #update-calendar {
        min-width: 140px;
        min-height: 38px;
        font-weight: 700;
    }

    .kkpay-admin-calendar__message {
        min-height: 20px;
        margin-top: 8px;
        font-weight: 700;
    }

    @media (max-width: 782px) {
        .kkpay-admin-calendar__month-nav {
            grid-template-columns: 1fr;
        }

        .calendar-controls {
            grid-template-columns: repeat(2, minmax(120px, 1fr));
        }

        .custom-calendar .calendar-day {
            height: 84px;
            padding: 7px;
        }
    }
</style>
