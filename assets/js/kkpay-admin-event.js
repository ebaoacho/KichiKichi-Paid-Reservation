/* global kkpay_admin_event */
( function ( $ ) {
    'use strict';

    // 保存/キャンセル/返金ボタンの連打・多重タブ操作による重複リクエストを防ぐ
    // （サーバー側は冪等だが、監査ログの重複や無駄なAPI呼び出しを避けるための一次防御）。
    var isSubmitting = false;

    $( document ).ready( function () {
        if ( typeof kkpay_admin_event === 'undefined' ) {
            return;
        }

        $( document )
            .on( 'click', '#kkpay-event-start-btn', function () {
                saveStatus( 'open' );
            } )
            .on( 'click', '#kkpay-event-end-btn', function () {
                var message = 'この操作は取り消せません。\n\n'
                    + 'イベントを終了すると:\n'
                    + '・受付が完全に停止し、二度と受付を再開できなくなります\n'
                    + '・未決済の仮予約（ホールド）はすべて即時に無効化されます\n'
                    + '・ただし、この操作の直前に決済を開始していたお客様がいた場合、システムが決済成立を確認できた分は\n'
                    + '　失効させずにそのまま予約として確定させます（確定予約一覧に表示されます。決済のキャンセルはできません）\n\n'
                    + '本当にイベントを終了しますか？';
                if ( ! window.confirm( message ) ) {
                    return;
                }
                saveStatus( 'archived' );
            } )
            .on( 'click', '.kkpay-event-cancel-btn', function () {
                var id = $( this ).data( 'id' );
                if ( ! window.confirm( 'この予約をキャンセルしますか？（返金は行われません）' ) ) {
                    return;
                }
                postAction( 'kkpay_event_admin_cancel', { reservation_id: id, reason: 'admin_manual_cancel' } );
            } );
    } );

    function saveStatus( status ) {
        if ( isSubmitting ) {
            return;
        }
        isSubmitting = true;
        showMessage( '', false );

        $.post( kkpay_admin_event.ajax_url, {
            action: 'kkpay_event_save_status',
            nonce:  kkpay_admin_event.nonce,
            status: status,
        } )
        .done( function ( res ) {
            if ( ! res.success ) {
                isSubmitting = false;
                showMessage( ( res.data && res.data.message ) || '保存に失敗しました。', true );
                return;
            }
            window.location.reload();
        } )
        .fail( function () {
            isSubmitting = false;
            showMessage( 'ネットワークエラーが発生しました。', true );
        } );
    }

    function postAction( action, payload ) {
        if ( isSubmitting ) {
            return;
        }
        isSubmitting = true;
        showMessage( '', false );

        var data = $.extend( { action: action, nonce: kkpay_admin_event.nonce }, payload );

        $.post( kkpay_admin_event.ajax_url, data )
            .done( function ( res ) {
                if ( ! res.success ) {
                    isSubmitting = false;
                    showMessage( ( res.data && res.data.message ) || '操作に失敗しました。', true );
                    return;
                }
                window.location.reload();
            } )
            .fail( function () {
                isSubmitting = false;
                showMessage( 'ネットワークエラーが発生しました。', true );
            } );
    }

    function showMessage( msg, isError ) {
        var el = $( '#kkpay-event-action-message' );
        if ( ! msg ) {
            el.hide();
            return;
        }
        el.removeClass( 'notice-error notice-success' )
            .addClass( isError ? 'notice-error' : 'notice-success' )
            .text( msg )
            .show();
    }

}( jQuery ) );
