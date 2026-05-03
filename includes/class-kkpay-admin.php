<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 管理画面の UI レンダリングを担当する
 * AJAX ハンドラは Controllers/class-kkpay-admin-controller.php に分離済み
 */
class KKPAY_Admin {

    public static function add_menu() {
        add_menu_page(
            'キチキチ 決済予約',
            'キチキチ 決済予約',
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
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>キチキチ 決済予約システム</h1>
            <?php self::render_reservations_tab(); ?>
        </div>
        <?php
    }

    /** AdminController::ajax_load_admin_list からも呼ばれる */
    public static function render_reservations_tab() {
        $filter_date = sanitize_text_field( $_GET['filter_date'] ?? '' );
        $filter_slot = sanitize_text_field( $_GET['filter_slot'] ?? '' );

        $results = KKPAY_Reservation_Repository::get_list( $filter_date, $filter_slot );

        // 日付 → スロット → 予約行 の 2 段階グループに整理
        $by_date = array();
        foreach ( $results as $row ) {
            $by_date[ $row->reservation_date ][ $row->time_slot ][] = $row;
        }

        self::render_filter_form( $filter_date, $filter_slot );

        if ( empty( $by_date ) ) {
            echo '<p style="margin-top:16px;">該当する予約はありません。</p>';
            return;
        }

        $status_styles = array(
            'pending'  => 'background:#fff3e0;color:#e65c00;padding:2px 8px;border-radius:4px;font-size:0.85em;font-weight:600;',
            'paid'     => 'background:#e8f5e9;color:#1b5e20;padding:2px 8px;border-radius:4px;font-size:0.85em;font-weight:600;',
            'refunded' => 'background:#f5f5f5;color:#555;padding:2px 8px;border-radius:4px;font-size:0.85em;font-weight:600;',
        );

        foreach ( $by_date as $date => $slots ) {

            // 日付カードのサマリ計算（paid のみ）
            $date_people = 0;
            $date_amount = 0;
            foreach ( $slots as $rows ) {
                foreach ( $rows as $row ) {
                    if ( $row->payment_status === 'paid' ) {
                        $date_people += (int) $row->number_of_people;
                        $date_amount += (int) $row->amount;
                    }
                }
            }

            echo '<div style="margin-top:28px;border:1.5px solid var(--kkpay-border,#e5e7eb);border-radius:10px;overflow:hidden;">';

            // 日付ヘッダー
            printf(
                '<div style="background:var(--kkpay-red,#c8102e);color:#fff;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;">'
                . '<span style="font-size:1.1rem;font-weight:700;">%s</span>'
                . '<span style="font-size:0.9rem;opacity:0.9;">確定 %d名 &nbsp;／&nbsp; ¥%s</span>'
                . '</div>',
                esc_html( $date ),
                $date_people,
                number_format( $date_amount )
            );

            foreach ( $slots as $slot_key => $rows ) {
                $slot_label   = KKPAY_SLOT_LABELS['ja'][ $slot_key ] ?? $slot_key;
                $slot_people  = 0;
                $slot_amount  = 0;

                // スロットヘッダー
                echo '<div style="background:#fafafa;border-top:1px solid #e5e7eb;padding:8px 20px;">'
                    . '<span style="font-weight:600;font-size:0.9rem;color:#374151;">' . esc_html( $slot_label ) . '</span>'
                    . '</div>';

                echo '<table class="wp-list-table widefat" style="border:none;border-radius:0;">';
                echo '<thead><tr style="background:#f9f9f9;">'
                    . '<th style="width:50px;">ID</th>'
                    . '<th>氏名</th>'
                    . '<th>メール</th>'
                    . '<th style="width:50px;">人数</th>'
                    . '<th style="width:80px;">金額</th>'
                    . '<th style="width:90px;">決済状態</th>'
                    . '<th style="width:140px;">キャンセル日時</th>'
                    . '</tr></thead><tbody>';

                foreach ( $rows as $row ) {
                    if ( $row->payment_status === 'paid' ) {
                        $slot_people += (int) $row->number_of_people;
                        $slot_amount += (int) $row->amount;
                    }

                    $cancelled_at = $row->cancelled_at
                        ? '<span style="color:#999;">' . esc_html( $row->cancelled_at ) . '</span>'
                        : '–';

                    $style = $status_styles[ $row->payment_status ] ?? '';

                    echo '<tr>';
                    echo '<td>' . (int) $row->id . '</td>';
                    echo '<td>' . esc_html( $row->name ) . '</td>';
                    echo '<td>' . esc_html( $row->email ) . '</td>';
                    echo '<td style="text-align:center;">' . (int) $row->number_of_people . '</td>';
                    echo '<td>¥' . number_format( (int) $row->amount ) . '</td>';
                    echo '<td><span style="' . esc_attr( $style ) . '">' . esc_html( $row->payment_status ) . '</span></td>';
                    echo '<td>' . $cancelled_at . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';

                // スロット小計
                printf(
                    '<div style="background:#fff8f8;border-top:1px solid #fde8eb;padding:7px 20px;text-align:right;font-size:0.85rem;color:var(--kkpay-red,#c8102e);font-weight:600;">'
                    . '小計: %d名 &nbsp;¥%s'
                    . '</div>',
                    $slot_people,
                    number_format( $slot_amount )
                );
            }

            echo '</div>'; // 日付カード終了
        }
    }

    private static function render_filter_form( $filter_date, $filter_slot ) {
        $csv_url = add_query_arg( array(
            'action'      => 'kkpay_export_csv',
            'filter_date' => $filter_date,
            'filter_slot' => $filter_slot,
            'nonce'       => wp_create_nonce( 'kkpay_export' ),
        ), admin_url( 'admin-ajax.php' ) );
        ?>
        <form method="get" style="margin:20px 0 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <input type="hidden" name="page" value="kkpay-settings" />
            <label style="display:flex;align-items:center;gap:6px;font-weight:600;">
                日付
                <input type="date" name="filter_date" value="<?php echo esc_attr( $filter_date ); ?>"
                       style="font-size:0.95rem;padding:4px 8px;border:1px solid #ccc;border-radius:5px;" />
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-weight:600;">
                スロット
                <select name="filter_slot" style="font-size:0.95rem;padding:4px 8px;border:1px solid #ccc;border-radius:5px;">
                    <option value="">すべて</option>
                    <?php foreach ( array_keys( KKPAY_SLOT_TYPES ) as $k ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $filter_slot, $k ); ?>>
                            <?php echo esc_html( KKPAY_SLOT_LABELS['ja'][ $k ] ?? $k ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="button">絞り込み</button>
            <a href="?page=kkpay-settings" class="button">リセット</a>
            <a href="<?php echo esc_url( $csv_url ); ?>" class="button button-primary" style="margin-left:auto;">
                CSVエクスポート
            </a>
        </form>
        <?php
    }
}
