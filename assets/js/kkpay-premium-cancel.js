/* global kkpay_premium_cancel, kkpay_premium_cancel_token */
( function ( $ ) {
    'use strict';

    var token = ( typeof kkpay_premium_cancel_token !== 'undefined' ) ? kkpay_premium_cancel_token : '';

    if ( ! token ) {
        return;
    }

    $( document ).ready( function () {
        $( '#kkpay-premium-cancel-btn' ).on( 'click', function () {
            if ( ! window.confirm( 'Are you sure you want to cancel? / キャンセルしてよろしいですか？' ) ) {
                return;
            }

            var btn = $( this );
            btn.prop( 'disabled', true ).text( 'Processing... / 処理中...' );
            $( '#kkpay-premium-cancel-message' ).hide();

            $.post( kkpay_premium_cancel.ajax_url, {
                action:       'kkpay_premium_cancel_reservation',
                nonce:        kkpay_premium_cancel.nonce,
                cancel_token: token,
            } )
            .done( function ( res ) {
                if ( res.success ) {
                    var amount = parseInt( res.data.amount, 10 ) || 32;
                    $( '#kkpay-premium-cancel-result-msg' ).text(
                        res.data.refunded
                            ? 'A refund of USD ' + amount + ' has been processed. / USD ' + amount + ' の返金処理が完了しました。'
                            : 'Your reservation has been cancelled. No refund will be issued. / ご予約をキャンセルしました。返金はございません。'
                    );
                    $( '#kkpay-premium-cancel-section' ).hide();
                    $( '#kkpay-premium-cancel-success-section' ).show();
                } else {
                    $( '#kkpay-premium-cancel-message' )
                        .text( res.data.message || 'An error occurred. / エラーが発生しました。' )
                        .addClass( 'kkpay-error' )
                        .show();
                    btn.prop( 'disabled', false ).text( 'Cancel Reservation / 予約をキャンセルする' );
                }
            } )
            .fail( function () {
                $( '#kkpay-premium-cancel-message' )
                    .text( 'Network error. / ネットワークエラー。' )
                    .addClass( 'kkpay-error' )
                    .show();
                btn.prop( 'disabled', false ).text( 'Cancel Reservation / 予約をキャンセルする' );
            } );
        } );
    } );

}( jQuery ) );
