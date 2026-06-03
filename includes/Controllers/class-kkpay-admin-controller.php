<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Admin_Controller {

    public static function ajax_load_admin_list() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => self::admin_message( 'unauthorized' ) ) );
            return;
        }

        ob_start();
        KKPAY_Admin::render_reservations_tab();
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }

    public static function ajax_export_csv() {
        check_ajax_referer( 'kkpay_export', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( self::admin_message( 'unauthorized' ) );
        }

        $filter_date = sanitize_text_field( $_GET['filter_date'] ?? '' );
        $filter_slot = sanitize_text_field( $_GET['filter_slot'] ?? '' );
        $results     = KKPAY_Reservation_Repository::get_list_as_array( $filter_date, $filter_slot );

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="kkpay_reservations_' . ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tokyo' ) ) )->format( 'Ymd_His' ) . '.csv"' );
        echo "\xEF\xBB\xBF";

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( '予約ID', '予約種別', '席種', '日付', 'スロット', '名前', 'メール', '席数', '金額', '決済ステータス', '言語', '作成日時', 'キャンセル日時' ) );
        $payment_status_labels = array(
            'pending'  => '決済待ち',
            'paid'     => '入金済み',
            'refunded' => '返金済み',
            'unpaid'   => '未入金',
        );

        foreach ( $results as $row ) {
            fputcsv( $out, array(
                $row['id'],
                $row['reservation_type'] ?? '',
                $row['seating_preference'] ?? '',
                $row['reservation_date'],
                KKPAY_SLOT_LABELS['ja'][ $row['time_slot'] ] ?? $row['time_slot'],
                $row['name'],
                $row['email'],
                $row['number_of_people'],
                $row['amount'],
                $payment_status_labels[ $row['payment_status'] ] ?? $row['payment_status'],
                $row['language'],
                $row['created_at'],
                $row['cancelled_at'] ?? '',
            ) );
        }

        fclose( $out );
        exit;
    }

    public static function ajax_save_slot_capacity() {
        global $wpdb;

        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => self::admin_message( 'unauthorized' ) ) );
            return;
        }

        $raw   = wp_unslash( $_POST['dates'] ?? '[]' );
        $dates = json_decode( $raw, true );
        if ( ! is_array( $dates ) ) {
            wp_send_json_error( array( 'message' => self::admin_message( 'invalid_data' ) ) );
            return;
        }

        $wpdb->query( 'START TRANSACTION' );

        foreach ( $dates as $row ) {
            $date  = sanitize_text_field( $row['date'] ?? '' );
            $slots = is_array( $row['slots'] ?? null ) ? $row['slots'] : array();

            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                continue;
            }

            $calendar = KKPAY_Calendar_Repository::find_by_date( $date );
            $valid_slots = $calendar
                ? KKPAY_Calendar_Service::get_open_slot_keys_for_date( $date )
                : array_keys( KKPAY_SLOT_TYPES );

            foreach ( $valid_slots as $slot ) {
                if ( array_key_exists( $slot, $slots ) ) {
                    $seat_capacities = is_array( $slots[ $slot ] )
                        ? $slots[ $slot ]
                        : array( 'Bar' => $slots[ $slot ] );

                    foreach ( array( 'Bar', 'Table' ) as $seat ) {
                        if ( ! array_key_exists( $seat, $seat_capacities ) ) {
                            continue;
                        }

                        $capacity = min( self::max_capacity_for_seat( $seat ), max( 0, (int) $seat_capacities[ $seat ] ) );
                        $enabled  = $capacity > 0 ? 1 : 0;

                        $slot_capacity_result = KKPAY_Slot_Capacity_Repository::upsert( $date, $slot, $seat, $capacity, $enabled );
                        if ( is_wp_error( $slot_capacity_result ) ) {
                            $wpdb->query( 'ROLLBACK' );
                            error_log( '[KKPAY] Slot capacity save failed. date=' . $date . ' slot=' . $slot . ' seat=' . $seat . ' message=' . $slot_capacity_result->get_error_message() );
                            wp_send_json_error( array( 'message' => self::admin_message( 'save_failed' ) ) );
                            return;
                        }
                    }

                    if ( array_key_exists( 'Bar', $seat_capacities ) ) {
                        $bar_capacity   = min( self::max_capacity_for_seat( 'Bar' ), max( 0, (int) $seat_capacities['Bar'] ) );
                        $accepted_result = KKPAY_Accepted_Dates_Repository::upsert_slot( $date, $slot, $bar_capacity, $bar_capacity > 0 ? 1 : 0 );
                        if ( $accepted_result === false ) {
                            $wpdb->query( 'ROLLBACK' );
                            error_log( '[KKPAY] Accepted dates capacity save failed. date=' . $date . ' slot=' . $slot . ' message=' . $wpdb->last_error );
                            wp_send_json_error( array( 'message' => self::admin_message( 'save_failed' ) ) );
                            return;
                        }
                    }
                } else {
                    // Missing open-slot payload means the admin UI did not submit that slot; disable it instead of leaving stale capacity.
                    foreach ( array( 'Bar', 'Table' ) as $seat ) {
                        $slot_capacity_result = KKPAY_Slot_Capacity_Repository::upsert( $date, $slot, $seat, 0, 0 );
                        if ( is_wp_error( $slot_capacity_result ) ) {
                            $wpdb->query( 'ROLLBACK' );
                            error_log( '[KKPAY] Slot capacity disable failed. date=' . $date . ' slot=' . $slot . ' seat=' . $seat . ' message=' . $slot_capacity_result->get_error_message() );
                            wp_send_json_error( array( 'message' => self::admin_message( 'save_failed' ) ) );
                            return;
                        }
                    }
                    $accepted_result = KKPAY_Accepted_Dates_Repository::upsert_slot( $date, $slot, 0, 0 );
                    if ( $accepted_result === false ) {
                        $wpdb->query( 'ROLLBACK' );
                        error_log( '[KKPAY] Accepted dates slot disable failed. date=' . $date . ' slot=' . $slot . ' message=' . $wpdb->last_error );
                        wp_send_json_error( array( 'message' => self::admin_message( 'save_failed' ) ) );
                        return;
                    }
                }
            }

            foreach ( array_diff( array_keys( KKPAY_SLOT_TYPES ), $valid_slots ) as $closed_slot ) {
                foreach ( array( 'Bar', 'Table' ) as $seat ) {
                    $slot_capacity_result = KKPAY_Slot_Capacity_Repository::upsert( $date, $closed_slot, $seat, 0, 0 );
                    if ( is_wp_error( $slot_capacity_result ) ) {
                        $wpdb->query( 'ROLLBACK' );
                        error_log( '[KKPAY] Closed slot capacity disable failed. date=' . $date . ' slot=' . $closed_slot . ' seat=' . $seat . ' message=' . $slot_capacity_result->get_error_message() );
                        wp_send_json_error( array( 'message' => self::admin_message( 'save_failed' ) ) );
                        return;
                    }
                }
                $accepted_result = KKPAY_Accepted_Dates_Repository::upsert_slot( $date, $closed_slot, 0, 0 );
                if ( $accepted_result === false ) {
                    $wpdb->query( 'ROLLBACK' );
                    error_log( '[KKPAY] Accepted dates closed slot disable failed. date=' . $date . ' slot=' . $closed_slot . ' message=' . $wpdb->last_error );
                    wp_send_json_error( array( 'message' => self::admin_message( 'save_failed' ) ) );
                    return;
                }
            }
        }

        $wpdb->query( 'COMMIT' );

        wp_send_json_success( array( 'message' => self::admin_message( 'saved' ) ) );
    }

    public static function ajax_save_calendar_day() {
        global $wpdb;

        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => self::admin_message( 'unauthorized' ) ) );
            return;
        }

        $raw  = wp_unslash( $_POST['days'] ?? '[]' );
        $days = json_decode( $raw, true );
        if ( ! is_array( $days ) ) {
            wp_send_json_error( array( 'message' => self::admin_message( 'invalid_data' ) ) );
            return;
        }
        if ( empty( $days ) ) {
            wp_send_json_success( array( 'message' => self::admin_message( 'saved' ) ) );
            return;
        }

        $wpdb->query( 'START TRANSACTION' );

        foreach ( $days as $row ) {
            $date = sanitize_text_field( $row['date'] ?? '' );
            if ( ! self::is_valid_date( $date ) ) {
                // The admin UI generates date values; invalid payload rows are ignored defensively.
                continue;
            }

            $lunch   = ! empty( $row['lunch'] ) ? 1 : 0;
            $dinner  = ! empty( $row['dinner'] ) ? 1 : 0;
            $premium = ! empty( $row['premium'] ) ? 1 : 0;
            $result  = KKPAY_Calendar_Repository::upsert_day( $date, $lunch, $dinner, $premium );
            if ( is_wp_error( $result ) ) {
                $wpdb->query( 'ROLLBACK' );
                error_log( '[KKPAY] Calendar save failed. date=' . $date . ' message=' . $result->get_error_message() );
                wp_send_json_error( array( 'message' => self::admin_message( 'save_failed' ) ) );
                return;
            }
        }

        $wpdb->query( 'COMMIT' );

        wp_send_json_success( array( 'message' => self::admin_message( 'saved' ) ) );
    }

    private static function is_valid_date( $date ) {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return false;
        }

        $parts = explode( '-', $date );
        return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
    }

    private static function max_capacity_for_seat( $seat ) {
        return $seat === 'Table' ? KKPAY_TABLE_MAX_CAPACITY : KKPAY_MAX_CAPACITY;
    }

    private static function admin_message( $key ) {
        // Admin-only screens are operated by Japanese staff, so these messages intentionally do not use kkpay_msg().
        $messages = array(
            'unauthorized' => '権限がありません。',
            'invalid_data' => '不正なデータです。',
            'save_failed'  => '保存に失敗しました。',
            'saved'        => '保存しました。',
        );

        return $messages[ $key ] ?? $messages['save_failed'];
    }

}
