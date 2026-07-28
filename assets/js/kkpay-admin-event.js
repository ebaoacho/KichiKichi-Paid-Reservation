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
            .on( 'click', '#kkpay-event-create-btn', createEvent )
            .on( 'click', '#kkpay-event-add-date-btn', addDefaultDateSlots )
            .on( 'click', '.kkpay-event-remove-slot', function ( event ) {
                event.preventDefault();
                $( this ).closest( 'tr' ).remove();
            } )
            .on( 'click', '#kkpay-event-save-btn', saveEvent )
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
                postAction( 'kkpay_event_admin_cancel', { event_id: currentEventId(), reservation_id: id, reason: 'admin_manual_cancel' } );
            } );
    } );

    function currentEventId() {
        var editorId = parseInt( $( '#kkpay-event-draft-editor' ).data( 'event-id' ), 10 );
        return editorId || parseInt( kkpay_admin_event.event_id, 10 ) || 0;
    }

    function createEvent() {
        var title = $.trim( $( '#kkpay-event-new-title' ).val() );
        var eventDate = $( '#kkpay-event-new-date' ).val();
        if ( ! title || ! eventDate ) {
            showMessage( 'イベントタイトルと最初の開催日を入力してください。', true );
            return;
        }
        var signature = title + '|' + eventDate;
        var storedRequest = window.sessionStorage.getItem( 'kkpay_event_create_request' );
        var requestData = {};
        try {
            requestData = storedRequest ? JSON.parse( storedRequest ) : {};
        } catch ( error ) {
            window.sessionStorage.removeItem( 'kkpay_event_create_request' );
        }
        var requestKey = requestData.signature === signature ? requestData.key : '';
        if ( ! requestKey ) {
            requestKey = 'create-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2, 12 );
            window.sessionStorage.setItem( 'kkpay_event_create_request', JSON.stringify( { signature: signature, key: requestKey } ) );
        }
        postAction( 'kkpay_event_admin_create', { title: title, event_date: eventDate, request_key: requestKey }, function ( data ) {
            window.sessionStorage.removeItem( 'kkpay_event_create_request' );
            redirectToEvent( data.event_id );
        } );
    }

    function addDefaultDateSlots( event ) {
        event.preventDefault();
        var date = $( '#kkpay-event-add-date' ).val();
        if ( ! date ) {
            showMessage( '開催日を選択してください。', true );
            return;
        }
        [ '11:00', '12:30', '14:00' ].forEach( function ( time ) {
            if ( slotExists( date, time ) ) {
                return;
            }
            $( '#kkpay-event-slot-editor tbody' ).append(
                '<tr data-slot-id="0">'
                + '<td><input class="kkpay-event-slot-date" type="date" value="' + escapeHtml( date ) + '"></td>'
                + '<td><input class="kkpay-event-slot-time" type="time" value="' + time + '"></td>'
                + '<td><input class="kkpay-event-slot-capacity" type="number" min="1" max="' + parseInt( kkpay_admin_event.max_capacity, 10 ) + '" value="8"> 名</td>'
                + '<td><button class="button-link-delete kkpay-event-remove-slot">削除</button></td>'
                + '</tr>'
            );
        } );
    }

    function slotExists( date, time ) {
        var found = false;
        $( '#kkpay-event-slot-editor tbody tr' ).each( function () {
            if ( $( this ).find( '.kkpay-event-slot-date' ).val() === date
                && $( this ).find( '.kkpay-event-slot-time' ).val() === time ) {
                found = true;
            }
        } );
        return found;
    }

    function saveEvent( event ) {
        event.preventDefault();
        var slots = [];
        var seen = {};
        var invalid = false;
        $( '#kkpay-event-slot-editor tbody tr' ).each( function () {
            var row = $( this );
            var date = row.find( '.kkpay-event-slot-date' ).val();
            var time = row.find( '.kkpay-event-slot-time' ).val();
            var capacity = parseInt( row.find( '.kkpay-event-slot-capacity' ).val(), 10 );
            var key = date + ' ' + time;
            if ( ! date || ! time || ! capacity || seen[ key ] ) {
                invalid = true;
                return false;
            }
            seen[ key ] = true;
            slots.push( { id: parseInt( row.data( 'slot-id' ), 10 ) || 0, date: date, time: time, capacity: capacity } );
        } );
        if ( invalid ) {
            showMessage( '未入力または重複している開催枠があります。', true );
            return;
        }
        postAction( 'kkpay_event_admin_save', {
            event_id: currentEventId(),
            title: $( '#kkpay-event-title' ).val(),
            slots: JSON.stringify( slots ),
        } );
    }

    function saveStatus( status ) {
        if ( isSubmitting ) {
            return;
        }
        isSubmitting = true;
        showMessage( '', false );

        $.post( kkpay_admin_event.ajax_url, {
            action: 'kkpay_event_save_status',
            nonce:  kkpay_admin_event.nonce,
            event_id: currentEventId(),
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

    function postAction( action, payload, onSuccess ) {
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
                if ( onSuccess ) {
                    onSuccess( res.data || {} );
                } else {
                    window.location.reload();
                }
            } )
            .fail( function () {
                isSubmitting = false;
                showMessage( 'ネットワークエラーが発生しました。', true );
            } );
    }

    function redirectToEvent( eventId ) {
        var url = new URL( window.location.href );
        url.searchParams.set( 'tab', 'event_reservations' );
        url.searchParams.set( 'event_id', eventId );
        window.location.href = url.toString();
    }

    function escapeHtml( value ) {
        return $( '<div>' ).text( value ).html();
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
