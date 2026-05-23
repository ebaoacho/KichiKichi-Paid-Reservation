/* global kkpay_premium, Stripe, kkpay_premium_token */
( function ( $ ) {
    'use strict';

    var stripe, cardElement;
    var token = ( typeof kkpay_premium_token !== 'undefined' ) ? kkpay_premium_token : '';
    var currentClientSecret = '';
    var currentPaymentIntentId = '';

    if ( ! token ) {
        return;
    }

    $( document ).ready( function () {
        var loadingEl = $( '#kkpay-premium-loading' );
        var formEl    = $( '#kkpay-premium-form' );

        if ( typeof Stripe === 'undefined' || ! kkpay_premium.stripe_pk ) {
            loadingEl.text( 'Payment system unavailable. / 決済システムが利用できません。' ).addClass( 'kkpay-error' );
            return;
        }

        stripe      = Stripe( kkpay_premium.stripe_pk );
        var elements = stripe.elements();
        cardElement = elements.create( 'card', {
            style: {
                base: { fontSize: '16px', color: '#32325d' },
            },
        } );
        cardElement.mount( '#kkpay-premium-card-element' );

        cardElement.on( 'change', function ( event ) {
            var errEl = $( '#kkpay-premium-card-errors' );
            errEl.text( event.error ? event.error.message : '' );
        } );

        loadingEl.hide();
        formEl.show();
        updateAmountDisplay();

        $( '#kkpay-premium-people' ).on( 'change', updateAmountDisplay );

        $( '#kkpay-premium-pay-btn' ).on( 'click', function () {
            handlePayment();
        } );
    } );

    function showMessage( msg, isError ) {
        var el = $( '#kkpay-premium-message' );
        el.removeClass( 'kkpay-error kkpay-success' )
            .addClass( isError ? 'kkpay-error' : 'kkpay-success' )
            .text( msg )
            .show();
    }

    function setLoading( loading ) {
        var btn = $( '#kkpay-premium-pay-btn' );
        btn.prop( 'disabled', loading );
        btn.text( loading ? 'Processing... / 処理中...' : 'Pay USD ' + calculateAmount() + ' / USD ' + calculateAmount() + ' を決済する' );
    }

    function calculateAmount() {
        var unit   = parseInt( kkpay_premium.unit_amount, 10 ) || 32;
        var people = parseInt( $( '#kkpay-premium-people' ).val(), 10 ) || 1;
        return unit * people;
    }

    function updateAmountDisplay() {
        var amount = calculateAmount();
        $( '#kkpay-premium-total-amount' ).text( 'Total / 合計: USD ' + amount );
        $( '#kkpay-premium-pay-btn' ).text( 'Pay USD ' + amount + ' / USD ' + amount + ' を決済する' );
    }

    function handlePayment() {
        var lang  = $( '#kkpay-premium-lang' ).val() || 'en';
        var name  = $.trim( $( '#kkpay-premium-name' ).val() );
        var email = $.trim( $( '#kkpay-premium-email' ).val() );
        var people = parseInt( $( '#kkpay-premium-people' ).val(), 10 ) || 0;

        if ( ! name ) {
            showMessage( 'Please enter your name. / お名前を入力してください。', true );
            return;
        }
        if ( ! /^[A-Za-z][A-Za-z .'-]*$/.test( name ) || name.length > 100 ) {
            showMessage( 'Please enter your name using English letters only. / お名前はアルファベットで入力してください。', true );
            return;
        }
        if ( ! email ) {
            showMessage( 'Please enter your email. / メールアドレスを入力してください。', true );
            return;
        }
        if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
            showMessage( 'Please enter a valid email address. / 正しいメールアドレスを入力してください。', true );
            return;
        }
        if ( people < 1 || people > ( parseInt( kkpay_premium.max_people, 10 ) || 8 ) ) {
            showMessage( 'Please select the number of seats. / 席数を選択してください。', true );
            return;
        }

        setLoading( true );
        showMessage( '', false );

        if ( currentClientSecret && currentPaymentIntentId ) {
            confirmStripePayment( currentClientSecret, currentPaymentIntentId );
            return;
        }

        $.post( kkpay_premium.ajax_url, {
            action:        'kkpay_premium_create_payment_intent',
            nonce:         kkpay_premium.nonce,
            payment_token: token,
            language:      lang,
            name:          name,
            email:         email,
            number_of_people: people,
        } )
        .done( function ( res ) {
            if ( ! res.success ) {
                showMessage( res.data.message || 'Error', true );
                setLoading( false );
                return;
            }
            currentClientSecret = res.data.client_secret;
            currentPaymentIntentId = res.data.payment_intent_id;
            if ( res.data.locked_people ) {
                $( '#kkpay-premium-people' ).val( parseInt( res.data.locked_people, 10 ) ).prop( 'disabled', true );
            }
            if ( res.data.amount ) {
                $( '#kkpay-premium-total-amount' ).text( 'Total / 合計: USD ' + parseInt( res.data.amount, 10 ) );
                $( '#kkpay-premium-pay-btn' ).text( 'Pay USD ' + parseInt( res.data.amount, 10 ) + ' / USD ' + parseInt( res.data.amount, 10 ) + ' を決済する' );
            }
            $( '#kkpay-premium-lang, #kkpay-premium-name, #kkpay-premium-email, #kkpay-premium-people' ).prop( 'disabled', true );
            confirmStripePayment( res.data.client_secret, res.data.payment_intent_id );
        } )
        .fail( function () {
            showMessage( 'Network error. Please try again. / ネットワークエラー。再度お試しください。', true );
            setLoading( false );
        } );
    }

    function confirmStripePayment( clientSecret, piId ) {
        stripe.confirmCardPayment( clientSecret, {
            payment_method: { card: cardElement },
        } )
        .then( function ( result ) {
            if ( result.error ) {
                showMessage( result.error.message, true );
                setLoading( false );
                return;
            }
            if ( result.paymentIntent && result.paymentIntent.status === 'succeeded' ) {
                confirmWithServer( piId );
            } else {
                showMessage( 'Payment failed. / 決済に失敗しました。', true );
                setLoading( false );
            }
        } );
    }

    function confirmWithServer( piId ) {
        $.post( kkpay_premium.ajax_url, {
            action:            'kkpay_premium_confirm_payment',
            nonce:             kkpay_premium.nonce,
            payment_token:     token,
            payment_intent_id: piId,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                $( '#kkpay-premium-form-section' ).hide();
                $( '#kkpay-premium-success-section' ).show();
            } else {
                showMessage( res.data.message || 'Confirmation failed.', true );
                setLoading( false );
            }
        } )
        .fail( function () {
            showMessage( 'Network error during confirmation. / 確認中にネットワークエラーが発生しました。', true );
            setLoading( false );
        } );
    }

}( jQuery ) );
