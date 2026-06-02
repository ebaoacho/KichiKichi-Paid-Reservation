/* global jQuery */
( function ( $ ) {
    'use strict';

    $( function () {
        var $toggle = $( '#kkpay-same-day-show-cancelled' );

        if ( ! $toggle.length ) {
            return;
        }

        function applyCancelledVisibility() {
            var showCancelled = $toggle.prop( 'checked' );
            $( '[data-kkpay-cancelled="1"]' ).toggle( showCancelled );
        }

        $toggle.on( 'change', applyCancelledVisibility );
        applyCancelledVisibility();
    } );
}( jQuery ) );
