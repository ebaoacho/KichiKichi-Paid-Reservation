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
        wp_enqueue_script( 'kkpay-admin-capacity', KKPAY_PLUGIN_URL . 'assets/js/kkpay-admin-capacity.js', array( 'jquery' ), KKPAY_VERSION, true );
        wp_localize_script( 'kkpay-admin-capacity', 'kkpay_admin_cap', array(
            'nonce'       => wp_create_nonce( 'kkpay_nonce' ),
            'maxCapacity' => KKPAY_MAX_CAPACITY,
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
            </h2>
            <?php
            if ( $active_tab === 'seat_capacity' ) {
                self::render_seat_capacity_tab();
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

    public static function render_seat_capacity_tab() {
        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $today = new DateTimeImmutable( 'today', $tz );
        $from  = $today->format( 'Y-m-d' );
        $to    = $today->modify( '+90 days' )->format( 'Y-m-d' );

        $rows  = KKPAY_Accepted_Dates_Repository::get_capacity_range( $from, $to );
        $saved = array();
        foreach ( $rows as $row ) {
            $saved[ $row->reservation_date ][ $row->time_slot ] = (int) $row->capacity;
        }

        $reserved  = KKPAY_Reservation_Repository::sum_people_by_date_range( $from, $to );
        $slot_keys = array_keys( KKPAY_SLOT_TYPES );

        include KKPAY_PLUGIN_DIR . 'templates/admin/seat-capacity-tab.php';
    }
}
