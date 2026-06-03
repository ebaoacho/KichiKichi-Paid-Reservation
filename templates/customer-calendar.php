<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$weekday_labels = array(
    kkpay_msg( 'calendar_week_sun', $calendar_lang ),
    kkpay_msg( 'calendar_week_mon', $calendar_lang ),
    kkpay_msg( 'calendar_week_tue', $calendar_lang ),
    kkpay_msg( 'calendar_week_wed', $calendar_lang ),
    kkpay_msg( 'calendar_week_thu', $calendar_lang ),
    kkpay_msg( 'calendar_week_fri', $calendar_lang ),
    kkpay_msg( 'calendar_week_sat', $calendar_lang ),
);
$month_cursor   = $calendar_from;
$label_title    = kkpay_msg( 'calendar_title', $calendar_lang );
$label_legend   = kkpay_msg( 'calendar_legend', $calendar_lang );
$label_premium  = kkpay_msg( 'calendar_premium_available', $calendar_lang );
$label_open     = kkpay_msg( 'calendar_open', $calendar_lang );
$label_closed   = kkpay_msg( 'calendar_closed', $calendar_lang );
?>

<div class="kkpay-customer-calendar">
    <div class="kkpay-customer-calendar__header">
        <h2 class="kkpay-customer-calendar__title"><?php echo esc_html( $label_title ); ?></h2>
        <div class="kkpay-customer-calendar__legend" aria-label="<?php echo esc_attr( $label_legend ); ?>">
            <span><i class="kkpay-customer-calendar__swatch is-premium"></i><?php echo esc_html( $label_premium ); ?></span>
            <span><i class="kkpay-customer-calendar__swatch is-open"></i><?php echo esc_html( $label_open ); ?></span>
            <span><i class="kkpay-customer-calendar__swatch is-closed"></i><?php echo esc_html( $label_closed ); ?></span>
        </div>
    </div>

    <div class="kkpay-customer-calendar__months">
        <?php while ( $month_cursor <= $calendar_to ) : ?>
            <?php
            $month_start   = new DateTimeImmutable( $month_cursor->format( 'Y-m-01' ), $month_cursor->getTimezone() );
            $month_end     = $month_start->modify( 'last day of this month' );
            $grid_start    = $month_start->modify( '-' . (int) $month_start->format( 'w' ) . ' days' );
            $grid_end      = $month_end->modify( '+' . ( 6 - (int) $month_end->format( 'w' ) ) . ' days' );
            $current_day   = $grid_start;
            $current_month = $month_start->format( 'm' );
            ?>
            <section class="kkpay-customer-calendar__month" aria-label="<?php echo esc_attr( $month_start->format( 'Y-m' ) ); ?>">
                <h3 class="kkpay-customer-calendar__month-title"><?php echo esc_html( $month_start->format( 'Y-m' ) ); ?></h3>
                <div class="kkpay-customer-calendar__grid">
                    <?php foreach ( $weekday_labels as $label ) : ?>
                        <div class="kkpay-customer-calendar__weekday"><?php echo esc_html( $label ); ?></div>
                    <?php endforeach; ?>

                    <?php while ( $current_day <= $grid_end ) : ?>
                        <?php
                        $date_key      = $current_day->format( 'Y-m-d' );
                        $is_outside    = $current_day->format( 'm' ) !== $current_month;
                        $day           = $calendar_days[ $date_key ] ?? array(
                            'open'              => false,
                            'premium_available' => false,
                        );
                        $is_premium    = ! $is_outside && ! empty( $day['premium_available'] );
                        $is_open       = ! $is_outside && ! empty( $day['open'] );
                        $is_today      = $date_key === $today->format( 'Y-m-d' );
                        $state_class   = $is_premium ? 'is-premium' : ( $is_open ? 'is-open' : 'is-closed' );
                        $state_label   = $is_premium ? $label_premium : ( $is_open ? $label_open : $label_closed );
                        $outside_class = $is_outside ? ' is-outside-month' : '';
                        $today_class   = $is_today ? ' is-today' : '';
                        ?>
                        <div class="kkpay-customer-calendar__day <?php echo esc_attr( $state_class . $outside_class . $today_class ); ?>" <?php if ( $is_outside ) : ?>aria-hidden="true"<?php else : ?>aria-label="<?php echo esc_attr( $date_key . ' ' . $state_label ); ?>"<?php endif; ?>>
                            <?php if ( ! $is_outside ) : ?>
                                <span class="kkpay-customer-calendar__date"><?php echo esc_html( $current_day->format( 'j' ) ); ?></span>
                                <span class="kkpay-customer-calendar__state"><?php echo esc_html( $state_label ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php $current_day = $current_day->modify( '+1 day' ); ?>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php $month_cursor = $month_start->modify( '+1 month' ); ?>
        <?php endwhile; ?>
    </div>
</div>
