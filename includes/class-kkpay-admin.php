<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Admin {

    public static function add_menu() {
        add_menu_page(
            'キチキチ 決済予約',
            'キチキチ 決済予約',
            'manage_options',
            'kkpay-settings',
            array( __CLASS__, 'render_page' ),
            'dashicons-credit-card',
            30
        );
    }

    public static function register_settings() {
        register_setting( 'kkpay_settings_group', 'kkpay_stripe_pk', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'kkpay_settings_group', 'kkpay_stripe_sk', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'kkpay_settings_group', 'kkpay_stripe_webhook_secret', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'kkpay_settings_group', 'kkpay_from_email', array( 'sanitize_callback' => 'sanitize_email' ) );
        register_setting( 'kkpay_settings_group', 'kkpay_from_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'kkpay-settings' ) === false ) {
            return;
        }
        wp_enqueue_style( 'kkpay-admin', KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css', array(), KKPAY_VERSION );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = sanitize_text_field( $_GET['tab'] ?? 'settings' );
        ?>
        <div class="wrap">
            <h1>キチキチ 決済予約システム</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=kkpay-settings&tab=settings"
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">設定</a>
                <a href="?page=kkpay-settings&tab=reservations"
                   class="nav-tab <?php echo $active_tab === 'reservations' ? 'nav-tab-active' : ''; ?>">予約者リスト</a>
                <a href="?page=kkpay-settings&tab=calendar"
                   class="nav-tab <?php echo $active_tab === 'calendar' ? 'nav-tab-active' : ''; ?>">営業カレンダー</a>
            </h2>

            <?php if ( $active_tab === 'settings' ) : ?>
                <?php self::render_settings_tab(); ?>
            <?php elseif ( $active_tab === 'reservations' ) : ?>
                <?php self::render_reservations_tab(); ?>
            <?php elseif ( $active_tab === 'calendar' ) : ?>
                <?php self::render_calendar_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'kkpay_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th>Stripe 公開キー (pk_)</th>
                    <td>
                        <input type="text" name="kkpay_stripe_pk"
                               value="<?php echo esc_attr( get_option( 'kkpay_stripe_pk', '' ) ); ?>"
                               class="regular-text" placeholder="pk_live_..." />
                    </td>
                </tr>
                <tr>
                    <th>Stripe シークレットキー (sk_)</th>
                    <td>
                        <input type="password" name="kkpay_stripe_sk"
                               value="<?php echo esc_attr( get_option( 'kkpay_stripe_sk', '' ) ); ?>"
                               class="regular-text" placeholder="sk_live_..." />
                        <p class="description">このキーはフロントエンドには絶対に露出しません。</p>
                    </td>
                </tr>
                <tr>
                    <th>Stripe Webhook シークレット (whsec_)</th>
                    <td>
                        <input type="password" name="kkpay_stripe_webhook_secret"
                               value="<?php echo esc_attr( get_option( 'kkpay_stripe_webhook_secret', '' ) ); ?>"
                               class="regular-text" placeholder="whsec_..." />
                        <p class="description">Webhook URL: <code><?php echo esc_url( rest_url( 'kkpay/v1/webhook' ) ); ?></code></p>
                    </td>
                </tr>
                <tr>
                    <th>送信元メールアドレス</th>
                    <td>
                        <input type="email" name="kkpay_from_email"
                               value="<?php echo esc_attr( get_option( 'kkpay_from_email', get_option( 'admin_email' ) ) ); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th>送信元名</th>
                    <td>
                        <input type="text" name="kkpay_from_name"
                               value="<?php echo esc_attr( get_option( 'kkpay_from_name', 'KichiKichi' ) ); ?>"
                               class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button( '設定を保存' ); ?>
        </form>
        <?php
    }

    private static function render_reservations_tab() {
        global $wpdb;
        $table = $wpdb->prefix . 'kkpay_reservations';

        // フィルター
        $filter_date = sanitize_text_field( $_GET['filter_date'] ?? '' );
        $filter_slot = sanitize_text_field( $_GET['filter_slot'] ?? '' );

        $where  = 'WHERE 1=1';
        $params = array();

        if ( $filter_date ) {
            $where   .= ' AND reservation_date = %s';
            $params[] = $filter_date;
        }
        if ( $filter_slot && array_key_exists( $filter_slot, KKPAY_SLOT_TYPES ) ) {
            $where   .= ' AND time_slot = %s';
            $params[] = $filter_slot;
        }

        $query = "SELECT * FROM {$table} {$where} ORDER BY reservation_date ASC, time_slot ASC, created_at ASC";
        if ( $params ) {
            $results = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
        } else {
            $results = $wpdb->get_results( $query );
        }

        // フィルターフォーム
        ?>
        <form method="get" style="margin: 16px 0;">
            <input type="hidden" name="page" value="kkpay-settings" />
            <input type="hidden" name="tab" value="reservations" />
            <label>日付:
                <input type="date" name="filter_date" value="<?php echo esc_attr( $filter_date ); ?>" />
            </label>
            <label style="margin-left:12px;">スロット:
                <select name="filter_slot">
                    <option value="">すべて</option>
                    <?php foreach ( array_keys( KKPAY_SLOT_TYPES ) as $k ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>"
                            <?php selected( $filter_slot, $k ); ?>>
                            <?php echo esc_html( KKPAY_SLOT_LABELS['ja'][ $k ] ?? $k ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="button">絞り込み</button>
            <a href="?page=kkpay-settings&tab=reservations" class="button">リセット</a>

            <?php
            // CSV エクスポートリンク
            $csv_url = add_query_arg( array(
                'action'      => 'kkpay_export_csv',
                'filter_date' => $filter_date,
                'filter_slot' => $filter_slot,
                'nonce'       => wp_create_nonce( 'kkpay_export' ),
            ), admin_url( 'admin-ajax.php' ) );
            ?>
            <a href="<?php echo esc_url( $csv_url ); ?>" class="button button-primary" style="margin-left:12px;">CSVエクスポート</a>
        </form>
        <?php

        if ( empty( $results ) ) {
            echo '<p>予約はありません。</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>予約日</th><th>スロット</th><th>氏名</th><th>メール</th>'
            . '<th>人数</th><th>決済状態</th><th>キャンセル日時</th></tr></thead><tbody>';

        $current_group = '';
        $group_total   = 0;

        foreach ( $results as $row ) {
            $group_key = $row->reservation_date . '_' . $row->time_slot;

            if ( $group_key !== $current_group ) {
                if ( $current_group !== '' ) {
                    echo '<tr class="kkpay-subtotal"><td colspan="5"><strong>小計</strong></td>'
                        . '<td><strong>' . (int) $group_total . '名</strong></td><td colspan="2"></td></tr>';
                }
                $slot_label    = KKPAY_SLOT_LABELS['ja'][ $row->time_slot ] ?? $row->time_slot;
                $current_group = $group_key;
                $group_total   = 0;
                echo '<tr class="kkpay-group-header"><td colspan="8">'
                    . esc_html( $row->reservation_date ) . ' ' . esc_html( $slot_label )
                    . '</td></tr>';
            }

            $group_total += (int) $row->number_of_people;

            $status_label = array(
                'pending'  => '<span style="color:#e65c00">pending</span>',
                'paid'     => '<span style="color:#007a00">paid</span>',
                'refunded' => '<span style="color:#555">refunded</span>',
            )[ $row->payment_status ] ?? esc_html( $row->payment_status );

            echo '<tr>';
            echo '<td>' . (int) $row->id . '</td>';
            echo '<td>' . esc_html( $row->reservation_date ) . '</td>';
            echo '<td>' . esc_html( KKPAY_SLOT_LABELS['ja'][ $row->time_slot ] ?? $row->time_slot ) . '</td>';
            echo '<td>' . esc_html( $row->name ) . '</td>';
            echo '<td>' . esc_html( $row->email ) . '</td>';
            echo '<td>' . (int) $row->number_of_people . '</td>';
            echo '<td>' . $status_label . '</td>';
            echo '<td>' . esc_html( $row->cancelled_at ?? '' ) . '</td>';
            echo '</tr>';
        }

        echo '<tr class="kkpay-subtotal"><td colspan="5"><strong>小計</strong></td>'
            . '<td><strong>' . (int) $group_total . '名</strong></td><td colspan="2"></td></tr>';
        echo '</tbody></table>';
    }

    private static function render_calendar_tab() {
        global $wpdb;
        $table = $wpdb->prefix . 'calendar';

        $tz   = new DateTimeZone( 'Asia/Tokyo' );
        $now  = new DateTimeImmutable( 'now', $tz );
        $from = $now->format( 'Y-m-d' );
        $to   = $now->modify( '+60 days' )->format( 'Y-m-d' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT date, lunch, dinner FROM {$table} WHERE date BETWEEN %s AND %s ORDER BY date ASC",
            $from, $to
        ) );

        echo '<p><em>既存プラグインの営業カレンダー（読み取り専用）</em></p>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>日付</th><th>ランチ</th><th>ディナー</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr>';
            echo '<td>' . esc_html( $row->date ) . '</td>';
            echo '<td>' . ( $row->lunch ? '○' : '×' ) . '</td>';
            echo '<td>' . ( $row->dinner ? '○' : '×' ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    // Ajax: 管理画面用予約リスト（動的ロード用）
    public static function ajax_load_admin_list() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        ob_start();
        self::render_reservations_tab();
        $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }
}

// CSV エクスポート
add_action( 'wp_ajax_kkpay_export_csv', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }
    check_ajax_referer( 'kkpay_export', 'nonce' );

    global $wpdb;
    $table = $wpdb->prefix . 'kkpay_reservations';

    $filter_date = sanitize_text_field( $_GET['filter_date'] ?? '' );
    $filter_slot = sanitize_text_field( $_GET['filter_slot'] ?? '' );

    $where  = 'WHERE 1=1';
    $params = array();
    if ( $filter_date ) {
        $where   .= ' AND reservation_date = %s';
        $params[] = $filter_date;
    }
    if ( $filter_slot && array_key_exists( $filter_slot, KKPAY_SLOT_TYPES ) ) {
        $where   .= ' AND time_slot = %s';
        $params[] = $filter_slot;
    }

    $query = "SELECT * FROM {$table} {$where} ORDER BY reservation_date ASC, time_slot ASC";
    if ( $params ) {
        $results = $wpdb->get_results( $wpdb->prepare( $query, ...$params ), ARRAY_A );
    } else {
        $results = $wpdb->get_results( $query, ARRAY_A );
    }

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="kkpay_reservations_' . date( 'Ymd_His' ) . '.csv"' );
    echo "\xEF\xBB\xBF"; // BOM for Excel

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'ID', '予約日', 'スロット', '氏名', 'メール', '人数', '決済状態', 'キャンセル日時', '言語' ) );
    foreach ( $results as $row ) {
        fputcsv( $out, array(
            $row['id'],
            $row['reservation_date'],
            KKPAY_SLOT_LABELS['ja'][ $row['time_slot'] ] ?? $row['time_slot'],
            $row['name'],
            $row['email'],
            $row['number_of_people'],
            $row['payment_status'],
            $row['cancelled_at'] ?? '',
            $row['language'],
        ) );
    }
    fclose( $out );
    exit;
} );
