<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Reservationの開催回作成と受付状態を管理する。
 * 受付状態の正本はkkpay_events.statusとする。
 */
class KKPAY_Event_Settings_Service {

    const STATUS_DRAFT    = 'draft';
    const STATUS_OPEN     = 'open';
    const STATUS_CLOSED   = 'closed';
    const STATUS_ARCHIVED = 'archived';

    public static function get_current_event() {
        return KKPAY_Event_Repository::find_open();
    }

    /**
     * 管理画面の表示対象を、明示されたevent_id、受付中、最新の順に返す。
     */
    public static function get_management_event( $event_id = null ) {
        if ( $event_id !== null && (int) $event_id > 0 ) {
            return KKPAY_Event_Repository::find( (int) $event_id );
        }

        $open = self::get_current_event();
        if ( $open ) {
            return $open;
        }

        return KKPAY_Event_Repository::find_latest();
    }

    public static function get_status( $event_id = null ) {
        $event = self::get_management_event( $event_id );
        if ( $event && self::is_valid_status( $event->status ) ) {
            return $event->status;
        }

        return self::STATUS_CLOSED;
    }

    public static function is_open( $event_id = null ) {
        if ( $event_id === null ) {
            return (bool) self::get_current_event();
        }

        $event = KKPAY_Event_Repository::find( (int) $event_id );
        return $event && $event->status === self::STATUS_OPEN;
    }

    /**
     * 新しいイベントを下書きとして作成する。
     *
     * @return array|WP_Error 作成したeventと初期slots
     */
    public static function create_draft_with_slots( $title, $request_key, array $slots ) {
        global $wpdb;

        $title = sanitize_text_field( $title );
        if ( $title === '' || mb_strlen( $title ) > 200 ) {
            return new WP_Error( 'invalid_event_title', 'イベントタイトルを200文字以内で入力してください。' );
        }

        $migration_key = 'admin_create:' . $request_key;
        $existing = KKPAY_Event_Repository::find_by_migration_key( $migration_key );
        if ( $existing ) {
            return array(
                'event' => $existing,
                'slots' => KKPAY_Event_Slot_Repository::get_all( $existing->id ),
            );
        }

        $now = self::now_jst();
        $wpdb->query( 'START TRANSACTION' );
        $event_id = KKPAY_Event_Repository::insert( array(
            'title'         => $title,
            'migration_key' => $migration_key,
            'unit_amount'   => KKPAY_EVENT_AMOUNT,
            'currency'      => KKPAY_EVENT_CURRENCY,
            'status'        => self::STATUS_DRAFT,
            'created_at'    => $now,
            'updated_at'    => $now,
        ) );
        if ( is_wp_error( $event_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            $existing = KKPAY_Event_Repository::find_by_migration_key( $migration_key );
            if ( $existing ) {
                return array(
                    'event' => $existing,
                    'slots' => KKPAY_Event_Slot_Repository::get_all( $existing->id ),
                );
            }
            return $event_id;
        }

        foreach ( $slots as $slot_data ) {
            $inserted = KKPAY_Event_Slot_Repository::insert( array(
                'event_id'   => $event_id,
                'event_date' => $slot_data['date'],
                'event_time' => $slot_data['time'],
                'capacity'   => $slot_data['capacity'],
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ) );
            if ( is_wp_error( $inserted ) ) {
                $wpdb->query( 'ROLLBACK' );
                return $inserted;
            }
        }

        $wpdb->query( 'COMMIT' );
        return array(
            'event' => KKPAY_Event_Repository::find( $event_id ),
            'slots' => KKPAY_Event_Slot_Repository::get_all( $event_id ),
        );
    }

    /** @return array|WP_Error */
    public static function save_draft( $event_id, $title, array $slots ) {
        global $wpdb;

        $now = self::now_jst();
        $wpdb->query( 'START TRANSACTION' );
        $event = KKPAY_Event_Repository::find_for_update( $event_id );
        if ( ! $event ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'event_not_found', 'イベントが見つかりません。' );
        }
        if ( $event->status !== self::STATUS_DRAFT ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'event_not_editable', '下書きイベントだけ編集できます。' );
        }

        $existing       = KKPAY_Event_Slot_Repository::get_all_for_update( $event->id );
        $existing_by_id = array();
        $existing_by_key = array();
        foreach ( $existing as $row ) {
            $existing_by_id[ (int) $row->id ] = $row;
            $existing_by_key[ $row->event_date . ' ' . $row->event_time ] = $row;
        }

        $kept_ids = array();
        foreach ( $slots as $slot_data ) {
            $key    = $slot_data['date'] . ' ' . $slot_data['time'];
            $target = null;
            if ( $slot_data['id'] > 0 ) {
                if ( ! isset( $existing_by_id[ $slot_data['id'] ] ) ) {
                    $wpdb->query( 'ROLLBACK' );
                    return new WP_Error( 'event_slot_mismatch', '別イベントの開催枠は更新できません。' );
                }
                $target = $existing_by_id[ $slot_data['id'] ];
            } elseif ( isset( $existing_by_key[ $key ] ) ) {
                $target = $existing_by_key[ $key ];
            }

            if ( $target ) {
                $held = KKPAY_Event_Hold_Repository::sum_active_guests_for_slot( $target->id, $now );
                $confirmed = KKPAY_Event_Reservation_Repository::sum_confirmed_guests_for_slot( $target->id );
                if ( $slot_data['capacity'] < $held + $confirmed ) {
                    $wpdb->query( 'ROLLBACK' );
                    return new WP_Error( 'capacity_below_usage', '定員を予約済み・仮押さえ中の人数未満には変更できません。' );
                }
                $updated = KKPAY_Event_Slot_Repository::update( $target->id, $event->id, array(
                    'event_date' => $slot_data['date'],
                    'event_time' => $slot_data['time'],
                    'capacity'   => $slot_data['capacity'],
                    'status'     => 'active',
                    'updated_at' => $now,
                ) );
                if ( is_wp_error( $updated ) ) {
                    $wpdb->query( 'ROLLBACK' );
                    return $updated;
                }
                $kept_ids[ (int) $target->id ] = true;
            } else {
                $inserted = KKPAY_Event_Slot_Repository::insert( array(
                    'event_id'   => (int) $event->id,
                    'event_date' => $slot_data['date'],
                    'event_time' => $slot_data['time'],
                    'capacity'   => $slot_data['capacity'],
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ) );
                if ( is_wp_error( $inserted ) ) {
                    $wpdb->query( 'ROLLBACK' );
                    return $inserted;
                }
                $kept_ids[ $inserted ] = true;
            }
        }

        foreach ( $existing as $row ) {
            if ( isset( $kept_ids[ (int) $row->id ] ) ) {
                continue;
            }
            $active_holds = KKPAY_Event_Hold_Repository::sum_active_guests_for_slot( $row->id, $now );
            if ( $active_holds > 0 || KKPAY_Event_Reservation_Repository::count_by_slot( $row->id ) > 0 ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'event_slot_in_use', '予約または有効なホールドがある開催枠は削除できません。' );
            }
            $deleted = KKPAY_Event_Slot_Repository::delete( $row->id, $event->id );
            if ( is_wp_error( $deleted ) ) {
                $wpdb->query( 'ROLLBACK' );
                return $deleted;
            }
        }

        $updated = KKPAY_Event_Repository::update( $event->id, array( 'title' => $title, 'updated_at' => $now ) );
        if ( is_wp_error( $updated ) ) {
            $wpdb->query( 'ROLLBACK' );
            return $updated;
        }
        $wpdb->query( 'COMMIT' );

        return array(
            'event' => KKPAY_Event_Repository::find( $event->id ),
            'slots' => KKPAY_Event_Slot_Repository::get_all( $event->id ),
        );
    }

    /**
     * @return object|WP_Error 更新後イベント
     */
    public static function set_status( $status, $event_id = null ) {
        global $wpdb;

        if ( ! self::is_valid_status( $status ) ) {
            return new WP_Error( 'invalid_status', 'Invalid event reservation status.' );
        }

        $target = self::get_management_event( $event_id );
        if ( ! $target ) {
            return new WP_Error( 'event_not_found', 'Event not found.' );
        }

        $uses_open_lock = $status === self::STATUS_OPEN;
        if ( $uses_open_lock && ! KKPAY_Event_Repository::acquire_open_status_lock() ) {
            return new WP_Error( 'event_status_locked', 'Another event status update is in progress.' );
        }

        try {
            $wpdb->query( 'START TRANSACTION' );
            $event = KKPAY_Event_Repository::find_for_update( $target->id );
            if ( ! $event ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'event_not_found', 'Event not found.' );
            }

            if ( $event->status === $status ) {
                $wpdb->query( 'ROLLBACK' );
                return $event;
            }

            if ( ! self::transition_is_allowed( $event->status, $status ) ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'invalid_status_transition', 'This event status change is not allowed.' );
            }

            if ( $status === self::STATUS_OPEN ) {
                $readiness = self::validate_open_readiness( $event );
                if ( is_wp_error( $readiness ) ) {
                    $wpdb->query( 'ROLLBACK' );
                    return $readiness;
                }

                $other_open = KKPAY_Event_Repository::find_other_open_for_update( $event->id );
                if ( $other_open ) {
                    $wpdb->query( 'ROLLBACK' );
                    return new WP_Error( 'another_event_open', 'Another event is already accepting reservations.' );
                }
            }

            $updated = KKPAY_Event_Repository::update( $event->id, array(
                'status'     => $status,
                'updated_at' => self::now_jst(),
            ) );
            if ( is_wp_error( $updated ) ) {
                $wpdb->query( 'ROLLBACK' );
                return $updated;
            }

            $wpdb->query( 'COMMIT' );

            return KKPAY_Event_Repository::find( $event->id );
        } finally {
            if ( $uses_open_lock ) {
                KKPAY_Event_Repository::release_open_status_lock();
            }
        }
    }

    private static function validate_open_readiness( $event ) {
        if ( trim( $event->title ) === '' ) {
            return new WP_Error( 'event_title_required', 'Event title is required.' );
        }
        if ( (int) $event->unit_amount !== KKPAY_EVENT_AMOUNT || $event->currency !== KKPAY_EVENT_CURRENCY ) {
            return new WP_Error( 'event_price_invalid', 'Event price configuration is invalid.' );
        }
        if ( ! KKPAY_Stripe_Config::has_secret_key() ) {
            return new WP_Error( 'stripe_not_configured', 'Payment system is not configured.' );
        }
        if ( KKPAY_Event_Repository::count_future_active_slots( $event->id, self::now_jst() ) < 1 ) {
            return new WP_Error( 'event_slots_required', 'At least one future active session is required.' );
        }

        return true;
    }

    private static function transition_is_allowed( $from, $to ) {
        $allowed = array(
            self::STATUS_DRAFT    => array( self::STATUS_OPEN ),
            self::STATUS_OPEN     => array( self::STATUS_CLOSED, self::STATUS_ARCHIVED ),
            self::STATUS_CLOSED   => array( self::STATUS_OPEN, self::STATUS_ARCHIVED ),
            self::STATUS_ARCHIVED => array(),
        );

        return isset( $allowed[ $from ] ) && in_array( $to, $allowed[ $from ], true );
    }

    private static function is_valid_status( $status ) {
        return in_array( $status, array(
            self::STATUS_DRAFT,
            self::STATUS_OPEN,
            self::STATUS_CLOSED,
            self::STATUS_ARCHIVED,
        ), true );
    }

    private static function now_jst() {
        return ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tokyo' ) ) )->format( 'Y-m-d H:i:s' );
    }
}
