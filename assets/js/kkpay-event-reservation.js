/* global kkpay_event_reservation, Stripe */
( function ( $ ) {
    'use strict';

    var stripe, elements;
    var slots = [];
    var currentHoldToken = '';
    var currentClientSecret = '';
    var currentPaymentIntentId = '';
    var currentAmount = 0;
    var paymentElementMounted = false;
    var isSubmitting = false;
    var countdownInterval = null;
    var secondsLeft = 0;

    // ページリロード/誤操作で Stripe Elements のマウント状態と JS 変数が失われても、
    // 決済入力途中のホールドに戻れるように sessionStorage へ最小限の状態を残す。
    // (この情報だけでは予約は完成しない。確定は必ずサーバー側の hold_token/PaymentIntent 照合を通る)
    var LEGACY_STORAGE_KEY = 'kkpay_event_hold_v1';
    var STORAGE_KEY = 'kkpay_event_hold_v2_' + parseInt( kkpay_event_reservation.event_id, 10 );

    function saveHoldState( expiresInSeconds ) {
        try {
            sessionStorage.setItem( STORAGE_KEY, JSON.stringify( {
                holdToken:         currentHoldToken,
                clientSecret:      currentClientSecret,
                paymentIntentId:   currentPaymentIntentId,
                amount:            currentAmount,
                eventId:           parseInt( kkpay_event_reservation.event_id, 10 ),
                expiresAtClientMs: Date.now() + ( Math.max( 0, parseInt( expiresInSeconds, 10 ) || 0 ) * 1000 ),
            } ) );
        } catch ( e ) {
            // private browsing 等で sessionStorage が使えない場合は静かに諦める（再開機能なしで動作を継続）。
        }
    }

    function loadHoldState() {
        try {
            var raw = sessionStorage.getItem( STORAGE_KEY );
            return raw ? JSON.parse( raw ) : null;
        } catch ( e ) {
            return null;
        }
    }

    function clearHoldState() {
        try {
            sessionStorage.removeItem( STORAGE_KEY );
        } catch ( e ) {
            // noop
        }
    }

    $( document ).ready( function () {
        if ( typeof kkpay_event_reservation === 'undefined' ) {
            return;
        }

        if ( typeof Stripe === 'undefined' || ! kkpay_event_reservation.stripe_pk ) {
            showMessage( 'Payment system unavailable.', true );
            return;
        }

        stripe = Stripe( kkpay_event_reservation.stripe_pk );

        try {
            sessionStorage.removeItem( LEGACY_STORAGE_KEY );
        } catch ( e ) {
            // noop
        }

        var saved = loadHoldState();
        var savedSecondsLeft = saved ? Math.round( ( saved.expiresAtClientMs - Date.now() ) / 1000 ) : 0;

        if ( saved && saved.eventId === parseInt( kkpay_event_reservation.event_id, 10 )
            && saved.holdToken && saved.clientSecret && savedSecondsLeft > 0 ) {
            resumeHold( saved, savedSecondsLeft );
        } else {
            if ( saved ) {
                // 期限切れの残骸。次回ロード時に毎回誤って再開扱いにならないよう消しておく。
                clearHoldState();
            }
            loadSlots();
        }

        $( '#kkpay-event-date' ).on( 'change', renderTimeOptions );
        $( '#kkpay-event-date, #kkpay-event-time, #kkpay-event-guests' ).on( 'change', updateAmountDisplay );
        $( '#kkpay-event-pay-btn' ).on( 'click', handlePayClick );
        $( '#kkpay-event-start-over-btn' ).on( 'click', function () {
            clearHoldState();
            window.location.reload();
        } );
    } );

    function resumeHold( saved, initialSecondsLeft ) {
        currentHoldToken       = saved.holdToken;
        currentClientSecret    = saved.clientSecret;
        currentPaymentIntentId = saved.paymentIntentId;
        currentAmount          = saved.amount || 0;

        $( '#kkpay-event-loading' ).hide();
        $( '#kkpay-event-form' ).show();
        $( '#kkpay-event-date, #kkpay-event-time, #kkpay-event-guests, #kkpay-event-name, #kkpay-event-email, #kkpay-event-policy-agree' ).prop( 'disabled', true );
        $( '#kkpay-event-total-amount' ).text( 'Total: USD ' + currentAmount );
        $( '#kkpay-event-start-over-btn' ).show();
        showMessage( 'Resuming your reservation in progress. Please complete payment before the timer runs out.', false );

        mountPaymentElement( initialSecondsLeft );
    }

    // kkpay_event_reservation.messages (PHP側の KKPAY_EVENT_MESSAGES) を唯一の情報源とし、
    // フォールバック文言はネットワーク未疎通時など localize 自体が読めない極端なケース用に留める。
    // (showMessage() の引数名 `msg` と衝突するため、あえて `t` という別名にしている)
    function t( key, fallback ) {
        var messages = kkpay_event_reservation && kkpay_event_reservation.messages;
        return ( messages && messages[ key ] ) || fallback;
    }

    function loadSlots() {
        $.post( kkpay_event_reservation.ajax_url, {
            action: 'kkpay_event_get_available_slots',
            nonce:  kkpay_event_reservation.nonce,
            event_id: kkpay_event_reservation.event_id,
        } )
        .done( function ( res ) {
            if ( ! res.success ) {
                showLoadError( res.data && res.data.message ? res.data.message : t( 'closed', 'This event is no longer accepting reservations.' ) );
                return;
            }

            if ( parseInt( res.data.event_id, 10 ) !== parseInt( kkpay_event_reservation.event_id, 10 ) ) {
                showLoadError( 'This event is no longer accepting reservations.' );
                return;
            }

            slots = ( res.data.slots || [] ).filter( function ( slot ) {
                return slot.remaining > 0;
            } );

            if ( ! slots.length ) {
                showLoadError( 'All sessions are fully booked.' );
                return;
            }

            renderDateOptions();
            renderTimeOptions();
            updateAmountDisplay();

            $( '#kkpay-event-loading' ).hide();
            $( '#kkpay-event-form' ).show();
        } )
        .fail( function () {
            showLoadError( 'Network error. Please reload the page and try again.' );
        } );
    }

    function showLoadError( message ) {
        $( '#kkpay-event-loading' ).text( message ).addClass( 'kkpay-error' );
    }

    function uniqueDates() {
        var seen = {};
        var dates = [];
        slots.forEach( function ( slot ) {
            if ( ! seen[ slot.date ] ) {
                seen[ slot.date ] = true;
                dates.push( slot.date );
            }
        } );
        return dates;
    }

    function renderDateOptions() {
        var select = $( '#kkpay-event-date' );
        select.empty();
        uniqueDates().forEach( function ( date ) {
            select.append( $( '<option></option>' ).val( date ).text( date ) );
        } );
    }

    function renderTimeOptions() {
        var date   = $( '#kkpay-event-date' ).val();
        var select = $( '#kkpay-event-time' );
        select.empty();

        slots
            .filter( function ( slot ) { return slot.date === date; } )
            .forEach( function ( slot ) {
                select.append(
                    $( '<option></option>' )
                        .val( slot.slot_id )
                        .text( slot.time + ' (' + slot.remaining + ' seats left)' )
                );
            } );
    }

    function selectedSlot() {
        var slotId = parseInt( $( '#kkpay-event-time' ).val(), 10 );
        return slots.filter( function ( slot ) { return slot.slot_id === slotId; } )[ 0 ] || null;
    }

    function calculateAmount() {
        var unit   = parseInt( kkpay_event_reservation.unit_amount, 10 ) || 50;
        var guests = parseInt( $( '#kkpay-event-guests' ).val(), 10 ) || 1;
        return unit * guests;
    }

    function updateAmountDisplay() {
        var amount = calculateAmount();
        $( '#kkpay-event-total-amount' ).text( 'Total: USD ' + amount );
        if ( ! paymentElementMounted ) {
            $( '#kkpay-event-pay-btn' ).text( 'Pay Now' );
        }
    }

    function showMessage( msg, isError ) {
        var el = $( '#kkpay-event-message' );
        el.removeClass( 'kkpay-error kkpay-success' )
            .addClass( isError ? 'kkpay-error' : 'kkpay-success' )
            .text( msg )
            .show();
    }

    function setLoading( loading, label ) {
        var btn = $( '#kkpay-event-pay-btn' );
        btn.prop( 'disabled', loading );
        if ( label ) {
            btn.text( label );
        }
    }

    function validateForm() {
        var name  = $.trim( $( '#kkpay-event-name' ).val() );
        var email = $.trim( $( '#kkpay-event-email' ).val() );
        var slot  = selectedSlot();

        if ( ! slot ) {
            showMessage( 'Please select a date and time.', true );
            return null;
        }
        if ( ! name || ! /^[A-Za-z][A-Za-z .'-]*$/.test( name ) || name.length > 100 ) {
            showMessage( t( 'invalid_name', 'Please enter your name using English letters only.' ), true );
            return null;
        }
        if ( ! email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
            showMessage( t( 'invalid_email', 'Please enter a valid email address.' ), true );
            return null;
        }
        if ( ! $( '#kkpay-event-policy-agree' ).is( ':checked' ) ) {
            showMessage( t( 'policy_not_agreed', 'Please agree to the cancellation policy to continue.' ), true );
            return null;
        }

        return {
            name:    name,
            email:   email,
            slot_id: slot.slot_id,
            guests:  parseInt( $( '#kkpay-event-guests' ).val(), 10 ) || 1,
        };
    }

    function handlePayClick() {
        // 連打・複数タブからの多重送信を防ぐ（サーバー側の多重ホールド防止と合わせた多層防御）。
        if ( isSubmitting ) {
            return;
        }

        showMessage( '', false );

        if ( paymentElementMounted ) {
            confirmStripePayment();
            return;
        }

        var data = validateForm();
        if ( ! data ) {
            return;
        }

        isSubmitting = true;
        setLoading( true, 'Processing...' );

        $.post( kkpay_event_reservation.ajax_url, {
            action:                      'kkpay_event_create_hold',
            nonce:                       kkpay_event_reservation.nonce,
            event_id:                    kkpay_event_reservation.event_id,
            name:                        data.name,
            email:                       data.email,
            slot_id:                     data.slot_id,
            guests:                      data.guests,
            cancellation_policy_agreed: 1,
        } )
        .done( function ( res ) {
            if ( ! res.success ) {
                isSubmitting = false;
                showMessage( ( res.data && res.data.message ) || 'Unable to reserve this session.', true );
                setLoading( false );
                return;
            }
            if ( parseInt( res.data.event_id, 10 ) !== parseInt( kkpay_event_reservation.event_id, 10 ) ) {
                isSubmitting = false;
                showMessage( 'This event is no longer accepting reservations.', true );
                setLoading( false );
                return;
            }

            currentHoldToken       = res.data.hold_token;
            currentClientSecret    = res.data.client_secret;
            currentPaymentIntentId = res.data.payment_intent_id;
            currentAmount          = parseInt( res.data.amount, 10 ) || calculateAmount();

            var expiresInSeconds = parseInt( res.data.expires_in_seconds, 10 );
            if ( isNaN( expiresInSeconds ) || expiresInSeconds < 0 ) {
                expiresInSeconds = ( parseInt( kkpay_event_reservation.hold_minutes, 10 ) || 5 ) * 60;
            }
            saveHoldState( expiresInSeconds );

            $( '#kkpay-event-date, #kkpay-event-time, #kkpay-event-guests, #kkpay-event-name, #kkpay-event-email, #kkpay-event-policy-agree' ).prop( 'disabled', true );
            $( '#kkpay-event-start-over-btn' ).show();

            isSubmitting = false;
            mountPaymentElement( expiresInSeconds );
        } )
        .fail( function () {
            isSubmitting = false;
            showMessage( 'Network error. Please try again.', true );
            setLoading( false );
        } );
    }

    function mountPaymentElement( initialSecondsLeft ) {
        elements = stripe.elements( { clientSecret: currentClientSecret } );
        var paymentElement = elements.create( 'payment' );
        paymentElement.mount( '#kkpay-event-payment-element' );

        paymentElement.on( 'change', function ( event ) {
            $( '#kkpay-event-payment-errors' ).text( event.error ? event.error.message : '' );
        } );

        $( '#kkpay-event-payment-section' ).show();
        paymentElementMounted = true;
        setLoading( false, 'Confirm Payment - USD ' + currentAmount );

        if ( typeof initialSecondsLeft === 'number' && initialSecondsLeft > 0 ) {
            startCountdown( initialSecondsLeft );
        }
    }

    function startCountdown( initialSecondsLeft ) {
        stopCountdown();
        secondsLeft = initialSecondsLeft;
        $( '#kkpay-event-countdown' ).show();
        renderCountdown();
        countdownInterval = setInterval( function () {
            secondsLeft--;
            renderCountdown();
            if ( secondsLeft <= 0 ) {
                stopCountdown();
                clearHoldState();
                showMessage( 'Your reservation hold has expired. Please start over to select a new session.', true );
                setLoading( true );
                $( '#kkpay-event-pay-btn' ).hide();
            }
        }, 1000 );
    }

    function stopCountdown() {
        if ( countdownInterval ) {
            clearInterval( countdownInterval );
            countdownInterval = null;
        }
    }

    function renderCountdown() {
        var s   = Math.max( 0, secondsLeft );
        var m   = Math.floor( s / 60 );
        var sec = s % 60;
        var el  = $( '#kkpay-event-countdown' );
        el.text( 'Time remaining to complete payment: ' + m + ':' + ( sec < 10 ? '0' : '' ) + sec );
        el.toggleClass( 'kkpay-event-countdown-urgent', s <= 60 );
    }

    function confirmStripePayment() {
        if ( isSubmitting ) {
            return;
        }
        isSubmitting = true;
        setLoading( true, 'Processing...' );

        stripe.confirmPayment( {
            elements: elements,
            confirmParams: {
                return_url: window.location.href,
            },
            redirect: 'if_required',
        } )
        .then( function ( result ) {
            if ( result.error ) {
                isSubmitting = false;
                showMessage( result.error.message, true );
                setLoading( false, 'Confirm Payment - USD ' + currentAmount );
                return;
            }
            if ( result.paymentIntent && result.paymentIntent.status === 'succeeded' ) {
                confirmWithServer();
            } else {
                isSubmitting = false;
                showMessage( 'Payment did not complete. Please try again.', true );
                setLoading( false, 'Confirm Payment - USD ' + currentAmount );
            }
        } );
    }

    function confirmWithServer() {
        $.post( kkpay_event_reservation.ajax_url, {
            action:             'kkpay_event_confirm_reservation',
            nonce:              kkpay_event_reservation.nonce,
            event_id:           kkpay_event_reservation.event_id,
            hold_token:         currentHoldToken,
            payment_intent_id:  currentPaymentIntentId,
        } )
        .done( function ( res ) {
            isSubmitting = false;
            if ( ! res.success ) {
                var code = res.data && res.data.code;
                if ( code === 'stripe_unavailable' ) {
                    // 決済自体は成功している可能性がある一時的なStripe疎通エラー。
                    // 「失敗」ではなく再確認を促す表示にし、そのままリトライできる状態を保つ
                    // （sessionStorageの hold 状態もクリアしない）。
                    showMessage( ( res.data && res.data.message ) || 'We could not verify your payment status right now. Please wait a moment and try again.', false );
                    setLoading( false, 'Retry Confirmation' );
                    return;
                }
                // それ以外（payment_mismatch / hold_not_found 等）は同じ hold_token での再試行では
                // 解消しない終端エラーのため、保存状態をクリアして「最初からやり直す」導線に寄せる。
                stopCountdown();
                clearHoldState();
                showMessage( ( res.data && res.data.message ) || 'Confirmation failed. Please contact us.', true );
                setLoading( false, 'Confirm Payment - USD ' + currentAmount );
                return;
            }

            stopCountdown();
            clearHoldState();

            $( '#kkpay-event-success-code' ).text( 'Reservation Code: ' + res.data.reservation_code );
            $( '#kkpay-event-success-details' ).text(
                res.data.event_title + ' — ' + res.data.date + ' ' + res.data.time
                + ' — ' + res.data.guests + ' guest(s) — Total: USD ' + res.data.amount
            );
            $( '#kkpay-event-form-section' ).hide();
            $( '#kkpay-event-success-section' ).show();
        } )
        .fail( function () {
            isSubmitting = false;
            showMessage( 'Network error during confirmation. Please contact us for assistance.', true );
            setLoading( false, 'Confirm Payment - USD ' + currentAmount );
        } );
    }

}( jQuery ) );
