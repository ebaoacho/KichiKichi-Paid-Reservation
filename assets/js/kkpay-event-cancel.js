/* global kkpay_event_cancel */
( function ( $ ) {
    'use strict';

    var isSubmitting = false;
    var currentCode  = '';
    var currentEmail = '';

    $( document ).ready( function () {
        if ( typeof kkpay_event_cancel === 'undefined' ) {
            return;
        }

        $( '#kkpay-event-cancel-lookup-btn' ).on( 'click', handleLookupClick );
        $( '#kkpay-event-cancel-confirm-btn' ).on( 'click', handleCancelClick );
    } );

    function showMessage( msg, isError ) {
        var el = $( '#kkpay-event-cancel-message' );
        if ( ! msg ) {
            el.hide();
            return;
        }
        el.removeClass( 'kkpay-error kkpay-success' )
            .addClass( isError ? 'kkpay-error' : 'kkpay-success' )
            .text( msg )
            .show();
    }

    function handleLookupClick() {
        if ( isSubmitting ) {
            return;
        }

        var code  = $.trim( $( '#kkpay-event-cancel-code' ).val() ).toUpperCase();
        var email = $.trim( $( '#kkpay-event-cancel-email' ).val() );

        showMessage( '', false );

        if ( ! code ) {
            showMessage( 'Please enter your reservation code.', true );
            return;
        }
        if ( ! email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
            showMessage( 'Please enter a valid email address.', true );
            return;
        }

        isSubmitting = true;
        $( '#kkpay-event-cancel-lookup-btn' ).prop( 'disabled', true ).text( 'Searching...' );

        $.post( kkpay_event_cancel.ajax_url, {
            action:            'kkpay_event_check_reservation',
            nonce:              kkpay_event_cancel.nonce,
            reservation_code:   code,
            email:               email,
        } )
        .done( function ( res ) {
            isSubmitting = false;
            $( '#kkpay-event-cancel-lookup-btn' ).prop( 'disabled', false ).text( 'Find My Reservation' );

            if ( ! res.success ) {
                showMessage( ( res.data && res.data.message ) || 'Reservation not found.', true );
                return;
            }

            currentCode  = code;
            currentEmail = email;
            renderDetails( res.data );
        } )
        .fail( function () {
            isSubmitting = false;
            $( '#kkpay-event-cancel-lookup-btn' ).prop( 'disabled', false ).text( 'Find My Reservation' );
            showMessage( 'Network error. Please try again.', true );
        } );
    }

    function renderDetails( data ) {
        var summary = $( '#kkpay-event-cancel-summary' );
        summary.empty();
        summary.append( $( '<p></p>' ).html( '<strong>Reservation Code:</strong> ' + escapeHtml( data.reservation_code ) ) );
        summary.append( $( '<p></p>' ).html( '<strong>Name:</strong> ' + escapeHtml( data.name ) ) );
        summary.append( $( '<p></p>' ).html( '<strong>Date:</strong> ' + escapeHtml( data.date ) ) );
        summary.append( $( '<p></p>' ).html( '<strong>Time:</strong> ' + escapeHtml( data.time ) ) );
        summary.append( $( '<p></p>' ).html( '<strong>Guests:</strong> ' + escapeHtml( String( data.guests ) ) ) );
        summary.append( $( '<p></p>' ).html( '<strong>Status:</strong> ' + escapeHtml( data.reservation_status ) ) );

        $( '#kkpay-event-cancel-lookup-section' ).hide();
        $( '#kkpay-event-cancel-details-section' ).show();

        if ( data.can_cancel ) {
            $( '#kkpay-event-cancel-action' ).show();
        } else {
            $( '#kkpay-event-cancel-action' ).hide();
            var reason = data.reservation_status !== 'CONFIRMED'
                ? 'This reservation has already been cancelled.'
                : 'This session has already taken place and can no longer be cancelled online. Please contact us directly.';
            showMessage( reason, true );
        }
    }

    function handleCancelClick() {
        if ( isSubmitting ) {
            return;
        }
        if ( ! window.confirm( 'Are you sure you want to cancel this reservation? No refund will be issued.' ) ) {
            return;
        }

        isSubmitting = true;
        $( '#kkpay-event-cancel-confirm-btn' ).prop( 'disabled', true ).text( 'Cancelling...' );

        $.post( kkpay_event_cancel.ajax_url, {
            action:            'kkpay_event_cancel_reservation',
            nonce:              kkpay_event_cancel.nonce,
            reservation_code:   currentCode,
            email:               currentEmail,
        } )
        .done( function ( res ) {
            isSubmitting = false;

            if ( ! res.success ) {
                $( '#kkpay-event-cancel-confirm-btn' ).prop( 'disabled', false ).text( 'Cancel Reservation' );
                showMessage( ( res.data && res.data.message ) || 'Unable to cancel this reservation.', true );
                return;
            }

            $( '#kkpay-event-cancel-details-section' ).hide();
            $( '#kkpay-event-cancel-success-section' ).show();
        } )
        .fail( function () {
            isSubmitting = false;
            $( '#kkpay-event-cancel-confirm-btn' ).prop( 'disabled', false ).text( 'Cancel Reservation' );
            showMessage( 'Network error. Please try again.', true );
        } );
    }

    function escapeHtml( value ) {
        return $( '<div></div>' ).text( value == null ? '' : value ).html();
    }

}( jQuery ) );
