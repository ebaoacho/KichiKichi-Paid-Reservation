<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$month_cursor   = $calendar_from;
$label_title    = kkpay_msg( 'calendar_title', $calendar_lang );
$label_legend   = kkpay_msg( 'calendar_legend', $calendar_lang );
$label_premium  = kkpay_msg( 'calendar_premium_available', $calendar_lang );
$label_prev     = kkpay_msg( 'calendar_previous_month', $calendar_lang );
$label_next     = kkpay_msg( 'calendar_next_month', $calendar_lang );
$label_lunch    = kkpay_msg( 'calendar_lunch', $calendar_lang );
$label_dinner   = kkpay_msg( 'calendar_dinner', $calendar_lang );
$label_open     = kkpay_msg( 'calendar_open', $calendar_lang );
$label_closed   = kkpay_msg( 'calendar_closed', $calendar_lang );
$label_meal_open = kkpay_msg( 'calendar_meal_open', $calendar_lang );
$label_meal_closed = kkpay_msg( 'calendar_meal_closed', $calendar_lang );
?>

<div class="kkpay-customer-calendar">
    <div class="kkpay-customer-calendar__header">
        <h2 class="kkpay-customer-calendar__title"><?php echo esc_html( $label_title ); ?></h2>
    </div>

    <div class="kkpay-customer-calendar__month-nav">
        <button type="button" class="kkpay-customer-calendar__nav-button" data-calendar-prev aria-label="<?php echo esc_attr( $label_prev ); ?>">&lt;</button>
        <span class="kkpay-customer-calendar__nav-title" data-calendar-title></span>
        <button type="button" class="kkpay-customer-calendar__nav-button" data-calendar-next aria-label="<?php echo esc_attr( $label_next ); ?>">&gt;</button>
    </div>

    <div class="kkpay-customer-calendar__months">
        <?php while ( $month_cursor <= $calendar_to ) : ?>
            <?php
            $month_start   = new DateTimeImmutable( $month_cursor->format( 'Y-m-01' ), $month_cursor->getTimezone() );
            $month_end     = $month_start->modify( 'last day of this month' );
            $current_day   = $month_start;
            ?>
            <section class="kkpay-customer-calendar__month" data-calendar-month data-month-label="<?php echo esc_attr( $month_start->format( 'Y-m' ) ); ?>" aria-label="<?php echo esc_attr( $month_start->format( 'Y-m' ) ); ?>">
                <h3 class="kkpay-customer-calendar__month-title"><?php echo esc_html( $month_start->format( 'Y-m' ) ); ?></h3>
                <div class="kkpay-customer-calendar__grid">
                    <?php while ( $current_day <= $month_end ) : ?>
                        <?php
                        $date_key      = $current_day->format( 'Y-m-d' );
                        $day           = $calendar_days[ $date_key ] ?? array(
                            'lunch'             => false,
                            'dinner'            => false,
                            'open'              => false,
                            'premium_available' => false,
                        );
                        $is_lunch_open = ! empty( $day['lunch'] );
                        $is_dinner_open = ! empty( $day['dinner'] );
                        $is_premium    = ! empty( $day['premium_available'] );
                        $is_open       = ! empty( $day['open'] );
                        $is_today      = $date_key === $today->format( 'Y-m-d' );
                        $state_class   = $is_premium ? 'is-premium' : ( $is_open ? 'is-open' : 'is-closed' );
                        $state_label   = $is_premium ? $label_premium : ( $is_open ? $label_open : $label_closed );
                        $lunch_label   = $is_lunch_open ? $label_meal_open : $label_meal_closed;
                        $dinner_label  = $is_dinner_open ? $label_meal_open : $label_meal_closed;
                        $today_class   = $is_today ? ' is-today' : '';
                        ?>
                        <div class="kkpay-customer-calendar__day <?php echo esc_attr( $state_class . $today_class ); ?>" aria-label="<?php echo esc_attr( $date_key . ' ' . $state_label ); ?>">
                            <span class="kkpay-customer-calendar__date"><?php echo esc_html( $current_day->format( 'j' ) ); ?></span>
                            <span class="kkpay-customer-calendar__meal <?php echo esc_attr( $is_lunch_open ? 'is-open' : 'is-closed' ); ?>">
                                <span class="kkpay-customer-calendar__emoji" aria-hidden="true">&#9728;&#65039;</span><br />
                                <span><?php echo esc_html( $lunch_label ); ?></span>
                            </span>
                            <span class="kkpay-customer-calendar__meal <?php echo esc_attr( $is_dinner_open ? 'is-open' : 'is-closed' ); ?>">
                                <span class="kkpay-customer-calendar__emoji" aria-hidden="true">&#127769;</span><br />
                                <span><?php echo esc_html( $dinner_label ); ?></span>
                            </span>
                            <?php if ( $is_premium ) : ?>
                                <span class="kkpay-customer-calendar__premium-label"><?php echo esc_html( $label_premium ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php $current_day = $current_day->modify( '+1 day' ); ?>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php $month_cursor = $month_start->modify( '+1 month' ); ?>
        <?php endwhile; ?>
    </div>

    <div class="kkpay-customer-calendar__legend" aria-label="<?php echo esc_attr( $label_legend ); ?>">
        <span><span class="kkpay-customer-calendar__legend-emoji" aria-hidden="true">&#9728;&#65039;</span><?php echo esc_html( $label_lunch ); ?></span>
        <span><span class="kkpay-customer-calendar__legend-emoji" aria-hidden="true">&#127769;</span><?php echo esc_html( $label_dinner ); ?></span>
        <span><span class="kkpay-customer-calendar__legend-premium" aria-hidden="true"></span><?php echo esc_html( $label_premium ); ?></span>
    </div>
</div>
