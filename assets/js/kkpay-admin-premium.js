/* global kkpay_admin_premium */
( function ( $ ) {
    'use strict';

    $( document ).ready( function () {

        // ------------------------------------------------------------------
        // 決済リンク発行
        // ------------------------------------------------------------------
        $( '#kkpay-premium-issue-link-btn' ).on( 'click', function () {
            var btn = $( this );
            btn.prop( 'disabled', true ).text( '発行中...' );
            clearActionMessage();

            $.post( kkpay_admin_premium.ajax_url, {
                action: 'kkpay_premium_issue_payment_link',
                nonce:  kkpay_admin_premium.nonce,
            } )
            .done( function ( res ) {
                if ( res.success ) {
                    $( '#kkpay-premium-link-url' ).text( res.data.url );
                    $( '#kkpay-premium-link-result' ).show();
                    showActionMessage( '決済リンクを発行しました。ページを更新するとリストに表示されます。', 'notice-success' );
                } else {
                    showActionMessage( res.data.message || 'エラーが発生しました。', 'notice-error' );
                }
            } )
            .fail( function () {
                showActionMessage( 'ネットワークエラーが発生しました。', 'notice-error' );
            } )
            .always( function () {
                btn.prop( 'disabled', false ).text( '決済リンク発行' );
            } );
        } );

        // ------------------------------------------------------------------
        // 日時確定
        // ------------------------------------------------------------------
        $( document ).on( 'click', '.kkpay-schedule-btn', function () {
            var btn      = $( this );
            var id       = btn.data( 'id' );
            var formWrap = btn.closest( '.kkpay-schedule-form' );
            var date     = formWrap.find( '.kkpay-schedule-date' ).val();
            var slot     = formWrap.find( '.kkpay-schedule-slot' ).val();
            var label    = $.trim( btn.text() ) || '日時確定';
            clearActionMessage();

            if ( ! date ) {
                showActionMessage( '日付を入力してください。', 'notice-error' );
                return;
            }

            btn.prop( 'disabled', true ).text( '確定中...' );

            $.post( kkpay_admin_premium.ajax_url, {
                action:           'kkpay_premium_schedule_reservation',
                nonce:            kkpay_admin_premium.nonce,
                premium_id:       id,
                reservation_date: date,
                time_slot:        slot,
            } )
            .done( function ( res ) {
                if ( res.success ) {
                    showActionMessage( 'ID ' + id + ' の日時を' + ( res.data.changed ? '変更' : '確定' ) + 'しました。', 'notice-success' );
                    refreshRow( id );
                } else {
                    showActionMessage( res.data.message || 'エラーが発生しました。', 'notice-error' );
                    btn.prop( 'disabled', false ).text( label );
                }
            } )
            .fail( function () {
                showActionMessage( 'ネットワークエラーが発生しました。', 'notice-error' );
                btn.prop( 'disabled', false ).text( label );
            } );
        } );

        // ------------------------------------------------------------------
        // キャンセルリンク発行
        // ------------------------------------------------------------------
        $( document ).on( 'click', '.kkpay-issue-cancel-link-btn', function () {
            var btn = $( this );
            var id  = btn.data( 'id' );
            clearActionMessage();
            btn.prop( 'disabled', true ).text( '発行中...' );

            $.post( kkpay_admin_premium.ajax_url, {
                action:     'kkpay_premium_issue_cancel_link',
                nonce:      kkpay_admin_premium.nonce,
                premium_id: id,
            } )
            .done( function ( res ) {
                if ( res.success ) {
                    $( '#kkpay-premium-cancel-link-url' ).text( res.data.url );
                    $( '#kkpay-premium-cancel-link-result' ).show();
                    showActionMessage( 'ID ' + id + ' のキャンセルリンクを発行しました。', 'notice-success' );
                    btn.replaceWith( '<span>キャンセルリンク発行済み</span>' );
                } else {
                    showActionMessage( res.data.message || 'エラーが発生しました。', 'notice-error' );
                    btn.prop( 'disabled', false ).text( 'キャンセルリンク発行' );
                }
            } )
            .fail( function () {
                showActionMessage( 'ネットワークエラーが発生しました。', 'notice-error' );
                btn.prop( 'disabled', false ).text( 'キャンセルリンク発行' );
            } );
        } );

        // ------------------------------------------------------------------
        // Helpers
        // ------------------------------------------------------------------
        function showActionMessage( msg, cls ) {
            var messageEl = $( '#kkpay-premium-action-message' );
            messageEl
                .removeClass( 'notice-success notice-error notice-warning' )
                .addClass( 'notice ' + cls )
                .empty()
                .show();
            $( '<p>' ).text( msg ).appendTo( messageEl );
        }

        function clearActionMessage() {
            $( '#kkpay-premium-action-message' ).hide().text( '' );
        }

        function refreshRow( id ) {
            // 行を再描画するためページリロード（シンプルな方法）
            window.location.reload();
        }
    } );

}( jQuery ) );
