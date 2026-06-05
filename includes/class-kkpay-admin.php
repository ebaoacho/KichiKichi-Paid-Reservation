<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Admin {

    public static function add_menu() {
        add_menu_page(
            'キチキチ 予約管理',
            'キチキチ 予約管理',
            'manage_options',
            'kkpay-settings',
            array( __CLASS__, 'render_page' ),
            'dashicons-calendar-alt',
            30
        );
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'kkpay-settings' ) === false ) {
            return;
        }
        wp_enqueue_style( 'kkpay-admin', KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css', array(), KKPAY_VERSION );
        wp_enqueue_style( 'kkpay-admin-reservations', KKPAY_PLUGIN_URL . 'assets/css/kkpay-admin-reservations.css', array( 'kkpay-admin' ), KKPAY_VERSION );
        wp_enqueue_script( 'kkpay-admin-capacity', KKPAY_PLUGIN_URL . 'assets/js/kkpay-admin-capacity.js', array( 'jquery' ), KKPAY_VERSION, true );
        wp_localize_script( 'kkpay-admin-capacity', 'kkpay_admin_cap', array(
            'nonce'            => wp_create_nonce( 'kkpay_nonce' ),
            'maxCapacity'      => KKPAY_MAX_CAPACITY,
            'barMaxCapacity'   => KKPAY_MAX_CAPACITY,
            'tableMaxCapacity' => KKPAY_TABLE_MAX_CAPACITY,
        ) );

        wp_enqueue_script( 'kkpay-admin-premium', KKPAY_PLUGIN_URL . 'assets/js/kkpay-admin-premium.js', array( 'jquery' ), KKPAY_VERSION, true );
        wp_localize_script( 'kkpay-admin-premium', 'kkpay_admin_premium', array(
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'kkpay_nonce' ),
            'export_nonce'   => wp_create_nonce( 'kkpay_premium_export' ),
            // 管理画面は日本語運用のため、時間枠ラベルも日本語固定にする。
            'slot_labels'    => KKPAY_SLOT_LABELS['ja'],
        ) );

        wp_enqueue_script( 'kkpay-admin-same-day', KKPAY_PLUGIN_URL . 'assets/js/kkpay-admin-same-day.js', array( 'jquery' ), KKPAY_VERSION, true );
        wp_enqueue_script( 'kkpay-admin-calendar', KKPAY_PLUGIN_URL . 'assets/js/kkpay-admin-calendar.js', array( 'jquery' ), KKPAY_VERSION, true );
        wp_localize_script( 'kkpay-admin-calendar', 'kkpay_admin_calendar', array(
            'nonce' => wp_create_nonce( 'kkpay_nonce' ),
        ) );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = sanitize_key( $_GET['tab'] ?? 'reservations' );
        ?>
        <div class="wrap">
            <h1>キチキチ 予約管理</h1>
            <h2 class="nav-tab-wrapper">
                <a class="nav-tab <?php echo $active_tab === 'reservations' ? 'nav-tab-active' : ''; ?>"
                   href="<?php echo esc_url( admin_url( 'admin.php?page=kkpay-settings' ) ); ?>">予約一覧</a>
                <a class="nav-tab <?php echo $active_tab === 'seat_capacity' ? 'nav-tab-active' : ''; ?>"
                   href="<?php echo esc_url( admin_url( 'admin.php?page=kkpay-settings&tab=seat_capacity' ) ); ?>">席数設定</a>
                <a class="nav-tab <?php echo $active_tab === 'premium_reservations' ? 'nav-tab-active' : ''; ?>"
                   href="<?php echo esc_url( admin_url( 'admin.php?page=kkpay-settings&tab=premium_reservations' ) ); ?>">スペシャルプレミアム予約</a>
                <a class="nav-tab <?php echo $active_tab === 'same_day_reservations' ? 'nav-tab-active' : ''; ?>"
                   href="<?php echo esc_url( admin_url( 'admin.php?page=kkpay-settings&tab=same_day_reservations' ) ); ?>">当日予約</a>
                <a class="nav-tab <?php echo $active_tab === 'calendar' ? 'nav-tab-active' : ''; ?>"
                   href="<?php echo esc_url( admin_url( 'admin.php?page=kkpay-settings&tab=calendar' ) ); ?>">営業日カレンダー</a>
            </h2>
            <?php
            if ( $active_tab === 'seat_capacity' ) {
                self::render_seat_capacity_tab();
            } elseif ( $active_tab === 'premium_reservations' ) {
                self::render_premium_reservations_tab();
            } elseif ( $active_tab === 'same_day_reservations' ) {
                self::render_same_day_reservations_tab();
            } elseif ( $active_tab === 'calendar' ) {
                self::render_calendar_tab();
            } else {
                self::render_reservations_tab();
            }
            ?>
        </div>
        <?php
    }

    public static function render_reservations_tab() {
        $filter_date = sanitize_text_field( $_GET['filter_date'] ?? '' );
        $filter_slot = sanitize_text_field( $_GET['filter_slot'] ?? '' );
        $results     = KKPAY_Reservation_Repository::get_list( $filter_date, $filter_slot );
        include KKPAY_PLUGIN_DIR . 'templates/admin/reservations-tab.php';
    }

    public static function render_premium_reservations_tab() {
        $filter_name = sanitize_text_field( $_GET['premium_name'] ?? '' );
        $results     = KKPAY_Premium_Reservation_Repository::get_list( $filter_name );
        include KKPAY_PLUGIN_DIR . 'templates/admin/premium-reservations-tab.php';
    }

    public static function render_same_day_reservations_tab() {
        $tz          = new DateTimeZone( 'Asia/Tokyo' );
        $today       = new DateTimeImmutable( 'today', $tz );
        $raw_date    = sanitize_text_field( wp_unslash( $_GET['same_day_date'] ?? '' ) );
        $filter_date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ? $raw_date : $today->format( 'Y-m-d' );
        $filter_slot = sanitize_text_field( wp_unslash( $_GET['same_day_slot'] ?? '' ) );
        $results     = KKPAY_Reservation_Repository::get_same_day_admin_list( $filter_date, $filter_slot );

        include KKPAY_PLUGIN_DIR . 'templates/admin/same-day-reservations-tab.php';
    }

    public static function render_seat_capacity_tab() {
        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $today = new DateTimeImmutable( 'today', $tz );
        $from  = $today->format( 'Y-m-d' );
        $to_date = self::two_months_later_end_of_month( $today );
        $to      = $to_date->format( 'Y-m-d' );
        $capacity_days = (int) $today->diff( $to_date )->format( '%a' );

        $rows  = KKPAY_Slot_Capacity_Repository::get_by_date_range( $from, $to );
        $saved = array();
        foreach ( $rows as $row ) {
            $saved[ $row->capacity_date ][ $row->time_slot ][ $row->seating_preference ] = array(
                'capacity' => (int) $row->capacity,
                'enabled'  => (int) $row->enabled,
            );
        }

        $reserved  = KKPAY_Reservation_Repository::sum_active_people_by_date_range_and_seat( $from, $to );
        $slot_keys = array_keys( KKPAY_SLOT_TYPES );
        $seat_keys = array( 'Bar', 'Table' );

        include KKPAY_PLUGIN_DIR . 'templates/admin/seat-capacity-tab.php';
    }

    public static function render_calendar_tab() {
        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $today = new DateTimeImmutable( 'today', $tz );
        $from  = $today->format( 'Y-m-d' );
        $to_date = self::two_months_later_end_of_month( $today );
        $to      = $to_date->format( 'Y-m-d' );
        $calendar_days = (int) $today->diff( $to_date )->format( '%a' );

        $calendar_rows = KKPAY_Calendar_Repository::get_range( $from, $to );
        $calendar      = array();
        foreach ( $calendar_rows as $row ) {
            $calendar[ $row->date ] = array(
                'lunch'   => (int) $row->lunch,
                'dinner'  => (int) $row->dinner,
                'premium' => (int) $row->premium,
            );
        }

        include KKPAY_PLUGIN_DIR . 'templates/admin/calendar-tab.php';
    }

    private static function two_months_later_end_of_month( DateTimeImmutable $today ) {
        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $year  = (int) $today->format( 'Y' );
        $month = (int) $today->format( 'n' ) + 2;

        while ( $month > 12 ) {
            $month -= 12;
            $year++;
        }

        return ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), $tz ) )
            ->modify( 'last day of this month' );
    }}
