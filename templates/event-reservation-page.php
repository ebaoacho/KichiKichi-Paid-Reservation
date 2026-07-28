<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$event_status = KKPAY_Event_Settings_Service::get_status();
?>

<div id="kkpay-event-wrap" class="kkpay-wrap kkpay-event-wrap">

    <div class="kkpay-event-hero">
        <h1 class="kkpay-event-title">Kichi Kichi Giant Omurice Event</h1>
        <p class="kkpay-event-tagline">Join Our Journey to a World Record!</p>
        <ul class="kkpay-event-highlights">
            <li>Watch Chef Motokichi create a giant Kichi Kichi Omurice right before your eyes.</li>
            <li>Share the giant Kichi Kichi Omurice together — eat, laugh, and celebrate with us.</li>
            <li>Enjoy the Kichi Kichi Happy Omurice Song.</li>
            <li>Take a photo with Chef Motokichi.</li>
            <li>Includes 1 drink and a special souvenir.</li>
            <li>Only 8 seats per session. First come, first served.</li>
        </ul>
        <p class="kkpay-event-price">USD 50 per seat</p>
    </div>

    <?php if ( $event_status === 'archived' ) : ?>

        <div class="kkpay-notice kkpay-event-closed-notice">
            <p>This event has ended. Thank you for your interest.</p>
        </div>

    <?php elseif ( $event_status !== 'open' ) : ?>

        <div class="kkpay-notice kkpay-event-closed-notice">
            <p><?php echo esc_html( kkpay_event_msg( 'closed' ) ); ?></p>
        </div>

    <?php else : ?>

        <div id="kkpay-event-form-section">
            <p id="kkpay-event-loading" class="kkpay-notice">Loading available sessions...</p>

            <div id="kkpay-event-form" style="display:none;">

                <div class="kkpay-field">
                    <label class="kkpay-label" for="kkpay-event-date">Date</label>
                    <select id="kkpay-event-date" class="kkpay-input"></select>
                </div>

                <div class="kkpay-field">
                    <label class="kkpay-label" for="kkpay-event-time">Time</label>
                    <select id="kkpay-event-time" class="kkpay-input"></select>
                </div>

                <div class="kkpay-field">
                    <label class="kkpay-label" for="kkpay-event-guests">Number of Guests</label>
                    <select id="kkpay-event-guests" class="kkpay-input">
                        <?php for ( $i = 1; $i <= KKPAY_EVENT_MAX_PEOPLE; $i++ ) : ?>
                            <option value="<?php echo (int) $i; ?>"><?php echo (int) $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="kkpay-field">
                    <label class="kkpay-label" for="kkpay-event-name">Full Name</label>
                    <input type="text" id="kkpay-event-name" class="kkpay-input" maxlength="100"
                           pattern="[A-Za-z .'-]+" title="Use English letters only." />
                </div>

                <div class="kkpay-field">
                    <label class="kkpay-label" for="kkpay-event-email">Email Address</label>
                    <input type="email" id="kkpay-event-email" class="kkpay-input" />
                </div>

                <div id="kkpay-event-total-amount" class="kkpay-amount-notice">Total: USD 50</div>

                <div class="kkpay-field kkpay-event-policy-field">
                    <label class="kkpay-event-policy-label">
                        <input type="checkbox" id="kkpay-event-policy-agree" />
                        I understand that this event reservation is prepaid and that no refund will be issued
                        after cancellation.
                    </label>
                </div>

                <div id="kkpay-event-payment-section" style="display:none;">
                    <div id="kkpay-event-countdown" class="kkpay-event-countdown" style="display:none;"></div>
                    <div class="kkpay-field">
                        <label class="kkpay-label">Payment Details</label>
                        <div id="kkpay-event-payment-element"></div>
                        <div id="kkpay-event-payment-errors" class="kkpay-error" role="alert"></div>
                    </div>
                </div>

                <div id="kkpay-event-message" class="kkpay-message" style="display:none;"></div>

                <button id="kkpay-event-pay-btn" class="kkpay-btn kkpay-btn-primary">
                    Pay Now
                </button>

                <button type="button" id="kkpay-event-start-over-btn" class="kkpay-event-start-over" style="display:none;">
                    Not you, or session expired? Start over
                </button>
            </div>
        </div>

        <div id="kkpay-event-success-section" style="display:none;" class="kkpay-success-box">
            <div class="kkpay-success-icon">&#10003;</div>
            <h2>Reservation Confirmed!</h2>
            <p id="kkpay-event-success-code"></p>
            <p id="kkpay-event-success-details"></p>
            <p>A confirmation email has been sent to you.</p>
            <?php $cancel_url = kkpay_find_shortcode_page_url( 'kkpay_event_cancel' ); ?>
            <?php if ( $cancel_url ) : ?>
                <p class="kkpay-event-note">
                    Need to cancel? You can cancel online anytime before the session using your reservation code:
                    <a href="<?php echo esc_url( $cancel_url ); ?>">Cancel Reservation</a>
                    (no refund will be issued).
                </p>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>
