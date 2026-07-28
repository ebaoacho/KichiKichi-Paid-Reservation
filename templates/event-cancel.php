<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="kkpay-event-cancel-wrap" class="kkpay-wrap kkpay-event-wrap">

    <h1 class="kkpay-title">Cancel Your Event Reservation</h1>
    <p class="kkpay-event-note" style="margin-bottom:16px;">
        Please note: this reservation is prepaid and no refund will be issued upon cancellation.
    </p>

    <div id="kkpay-event-cancel-lookup-section">
        <div class="kkpay-field">
            <label class="kkpay-label" for="kkpay-event-cancel-code">Reservation Code</label>
            <input type="text" id="kkpay-event-cancel-code" class="kkpay-input" placeholder="EVT-XXXXXXXX" />
        </div>

        <div class="kkpay-field">
            <label class="kkpay-label" for="kkpay-event-cancel-email">Email Address</label>
            <input type="email" id="kkpay-event-cancel-email" class="kkpay-input" />
        </div>

        <div id="kkpay-event-cancel-message" class="kkpay-message" style="display:none;"></div>

        <button id="kkpay-event-cancel-lookup-btn" class="kkpay-btn kkpay-btn-primary">
            Find My Reservation
        </button>
    </div>

    <div id="kkpay-event-cancel-details-section" style="display:none;">
        <div id="kkpay-event-cancel-summary" class="kkpay-summary"></div>

        <div id="kkpay-event-cancel-action" style="display:none;">
            <button id="kkpay-event-cancel-confirm-btn" class="kkpay-btn kkpay-btn-danger">
                Cancel Reservation
            </button>
        </div>
    </div>

    <div id="kkpay-event-cancel-success-section" style="display:none;" class="kkpay-success-box">
        <div class="kkpay-success-icon">&#10003;</div>
        <h2>Reservation Cancelled</h2>
        <p>Your reservation has been cancelled. No refund will be issued.</p>
    </div>

</div>
