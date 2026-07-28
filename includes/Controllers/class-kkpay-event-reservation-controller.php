<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Reservation（イベント予約）の AJAX エンドポイントを担当する。
 * 公開 AJAX: 顧客フォームから呼ばれる（空き枠取得・ホールド+PaymentIntent作成・予約確定・照会・キャンセル）
 * 管理画面 AJAX: manage_options 権限が必要（一覧・CSV・受付ステータス・手動キャンセル）
 * 返金は一切行わない方針のため、Stripe 返金APIを呼ぶエンドポイントは存在しない。
 */
class KKPAY_Event_Reservation_Controller {

    // ------------------------------------------------------------------
    // 公開 AJAX
    // ------------------------------------------------------------------

    public static function ajax_get_available_slots() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $event = KKPAY_Event_Settings_Service::get_current_event();
        if ( ! $event ) {
            self::send_closed_error();
        }

        $now   = ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tokyo' ) ) )->format( 'Y-m-d H:i:s' );
        $rows  = KKPAY_Event_Slot_Repository::find_all_with_remaining( $event->id, $now );
        $slots = array();
        foreach ( $rows as $row ) {
            $slots[] = array(
                'slot_id'   => (int) $row->id,
                'date'      => $row->event_date,
                'time'      => $row->event_time,
                'capacity'  => (int) $row->capacity,
                'remaining' => max( 0, (int) $row->remaining ),
            );
        }

        wp_send_json_success( array( 'slots' => $slots ) );
    }

    public static function ajax_create_hold() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $event = KKPAY_Event_Settings_Service::get_current_event();
        if ( ! $event ) {
            self::send_closed_error();
        }

        $data = KKPAY_Event_Reservation_Validator::validate_create_hold( $_POST, $event->id );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        if ( ! KKPAY_Stripe_Config::has_secret_key() ) {
            wp_send_json_error( array( 'message' => 'Payment system is not configured.' ) );
        }

        $pi = KKPAY_Event_Hold_Service::create_hold_and_payment_intent( $event->id, $data['slot_id'], $data['guests'], $data['name'], $data['email'] );
        if ( is_wp_error( $pi ) ) {
            wp_send_json_error( array( 'message' => $pi->get_error_message() ) );
        }

        wp_send_json_success( $pi );
    }

    public static function ajax_confirm_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        // 受付停止後でも、既に開始された決済は確定できなければならない（意図的にステータスチェックしない）。
        $data = KKPAY_Event_Reservation_Validator::validate_confirm( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        if ( ! KKPAY_Stripe_Config::has_secret_key() ) {
            wp_send_json_error( array( 'message' => 'Payment system is not configured.' ) );
        }

        $result = KKPAY_Event_Payment_Service::confirm_from_payment_intent( $data['payment_intent_id'], $data['hold_token'], 'browser_confirm' );
        if ( is_wp_error( $result ) ) {
            // code をフロントへ渡すことで、Stripe疎通エラー(stripe_unavailable)と
            // 本当の決済未完了(payment_not_succeeded)とで表示・挙動を分けられるようにする。
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
        }

        wp_send_json_success( self::build_reservation_response( $result ) );
    }

    /**
     * 顧客による予約照会（キャンセルページの照会ステップ）。返金は一切行わない方針のため、
     * ここでは reservation_status / can_cancel（開催日時を過ぎていないか）のみを判定して返す。
     */
    public static function ajax_check_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Event_Reservation_Validator::validate_customer_lookup( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $reservation = KKPAY_Event_Reservation_Service::find_for_customer( $data['reservation_code'], $data['email'] );
        if ( is_wp_error( $reservation ) ) {
            wp_send_json_error( array( 'message' => $reservation->get_error_message() ) );
        }

        wp_send_json_success( KKPAY_Event_Reservation_Service::build_customer_view( $reservation ) );
    }

    /**
     * 顧客自身によるキャンセル（返金なし）。
     */
    public static function ajax_cancel_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Event_Reservation_Validator::validate_customer_lookup( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $result = KKPAY_Event_Reservation_Service::cancel_by_customer( $data['reservation_code'], $data['email'] );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
        }

        wp_send_json_success( KKPAY_Event_Reservation_Service::build_customer_view( $result ) );
    }

    // ------------------------------------------------------------------
    // 管理画面 AJAX
    // ------------------------------------------------------------------

    public static function ajax_export_csv() {
        check_ajax_referer( 'kkpay_event_export', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $event_id = intval( $_GET['event_id'] ?? 0 );
        $event = KKPAY_Event_Settings_Service::get_management_event( $event_id > 0 ? $event_id : null );
        if ( ! $event ) {
            wp_die( 'Event not found.' );
        }

        $results  = KKPAY_Event_Reservation_Repository::get_list_as_array_by_event( $event->id );
        $slot_map = array();
        foreach ( KKPAY_Event_Slot_Repository::get_all( $event->id ) as $slot ) {
            $slot_map[ $slot->id ] = $slot;
        }

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="kkpay_event_reservations_' . ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tokyo' ) ) )->format( 'Ymd_His' ) . '.csv"' );
        echo "\xEF\xBB\xBF";

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'Reservation Code', 'Status', 'Name', 'Email', 'Date', 'Time', 'Guests', 'Amount', 'Currency', 'Payment Status', 'Payment Intent', 'Confirmed By', 'Overbooked', 'Confirmed At', 'Cancelled At', 'Refunded At', 'Created At' ) );

        foreach ( $results as $row ) {
            $slot = $slot_map[ $row['slot_id'] ] ?? null;
            fputcsv( $out, array(
                $row['reservation_code'],
                $row['reservation_status'],
                self::csv_safe( $row['name'] ),
                self::csv_safe( $row['email'] ),
                $slot ? $slot->event_date : '',
                $slot ? $slot->event_time : '',
                $row['guests'],
                $row['amount'],
                $row['currency'],
                $row['payment_status'],
                $row['payment_intent_id'],
                $row['confirmed_by'],
                ! empty( $row['is_overbooked'] ) ? 'yes' : 'no',
                $row['confirmed_at'],
                $row['cancelled_at'] ?? '',
                $row['refunded_at'] ?? '',
                $row['created_at'],
            ) );
        }

        fclose( $out );
        exit;
    }

    /**
     * CSVインジェクション対策: セルの先頭が Excel/Sheets が数式と解釈しうる記号
     * (= + - @ タブ CR) で始まる場合、先頭にシングルクォートを付けて数式として実行されないようにする。
     * name/email は基本Validatorで制限されるが、念のため出力側でも防御する。
     */
    private static function csv_safe( $value ) {
        $value = (string) $value;
        if ( $value !== '' && preg_match( '/^[=+\-@\t\r]/', $value ) ) {
            return "'" . $value;
        }
        return $value;
    }

    public static function ajax_save_status() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $data = KKPAY_Event_Reservation_Validator::validate_admin_status_update( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $event_id = intval( $_POST['event_id'] ?? 0 );
        $event = KKPAY_Event_Settings_Service::get_management_event( $event_id > 0 ? $event_id : null );
        if ( ! $event ) {
            wp_send_json_error( array( 'message' => 'Event not found.' ) );
        }

        $was_archived = $event->status === KKPAY_Event_Settings_Service::STATUS_ARCHIVED;
        $updated = KKPAY_Event_Settings_Service::set_status( $data['status'], $event->id );
        if ( is_wp_error( $updated ) ) {
            wp_send_json_error( array( 'message' => $updated->get_error_message() ) );
        }

        // イベント終了時は、受付停止だけでなく残っている未決済ホールドも即時失効させる
        // （イベントが終わった以上、あとから決済されても受け付けられないため）。
        $closed_holds = 0;
        if ( $data['status'] === KKPAY_Event_Settings_Service::STATUS_ARCHIVED && ! $was_archived ) {
            $closed_holds = KKPAY_Event_Hold_Service::hard_close( $event->id );
        }

        wp_send_json_success( array(
            'status'       => $updated->status,
            'closed_holds' => $closed_holds,
        ) );
    }

    public static function ajax_admin_cancel() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $data = KKPAY_Event_Reservation_Validator::validate_admin_cancel( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $event_id = intval( $_POST['event_id'] ?? 0 );
        $event = KKPAY_Event_Settings_Service::get_management_event( $event_id > 0 ? $event_id : null );
        if ( ! $event ) {
            wp_send_json_error( array( 'message' => 'Event not found.' ) );
        }

        $result = KKPAY_Event_Reservation_Service::admin_cancel( $data['reservation_id'], $event->id, $data['reason'] );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'cancelled' => true ) );
    }

    public static function ajax_admin_create_event() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $data = KKPAY_Event_Reservation_Validator::validate_admin_event_create( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }
        $event = KKPAY_Event_Settings_Service::create( $data['title'], $data['request_key'] );
        if ( is_wp_error( $event ) ) {
            wp_send_json_error( array( 'message' => $event->get_error_message() ) );
        }

        $initial_slots = array();
        foreach ( array( '11:00', '12:30', '14:00' ) as $time ) {
            $initial_slots[] = array(
                'id'       => 0,
                'date'     => $data['event_date'],
                'time'     => $time,
                'capacity' => KKPAY_EVENT_MAX_PEOPLE,
            );
        }
        $saved = KKPAY_Event_Settings_Service::save_draft( $event->id, $data['title'], $initial_slots );
        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( array( 'message' => $saved->get_error_message() ) );
        }

        wp_send_json_success( array( 'event_id' => (int) $event->id ) );
    }

    public static function ajax_admin_save_event() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $data = KKPAY_Event_Reservation_Validator::validate_admin_event_save( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }
        $saved = KKPAY_Event_Settings_Service::save_draft( $data['event_id'], $data['title'], $data['slots'] );
        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( array( 'message' => $saved->get_error_message() ) );
        }

        wp_send_json_success( array( 'event_id' => (int) $saved['event']->id ) );
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private static function send_closed_error() {
        wp_send_json_error( array(
            'code'    => 'event_reservation_closed',
            'message' => kkpay_event_msg( 'closed' ),
        ) );
    }

    private static function build_reservation_response( $reservation ) {
        $slot = KKPAY_Event_Slot_Repository::find( $reservation->slot_id );
        return array(
            'reservation_code' => $reservation->reservation_code,
            'name'             => $reservation->name,
            'email'            => $reservation->email,
            'guests'           => (int) $reservation->guests,
            'amount'           => (int) $reservation->amount,
            'currency'         => $reservation->currency,
            'date'             => $slot ? $slot->event_date : '',
            'time'             => $slot ? $slot->event_time : '',
        );
    }
}
