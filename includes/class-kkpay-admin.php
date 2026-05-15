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

        self::render_filter_form( $filter_date, $filter_slot );

        if ( empty( $results ) ) {
            echo '<p style="margin-top:16px;">予約が見つかりませんでした。</p>';
            return;
        }

        $by_date = array();
        foreach ( $results as $row ) {
            $by_date[ $row->reservation_date ][ $row->time_slot ][] = $row;
        }

        foreach ( $by_date as $date => $slots ) {
            $date_coming = 0;
            $date_paid   = 0;
            $date_amount = 0;

            foreach ( $slots as $rows ) {
                foreach ( $rows as $row ) {
                    if ( $row->payment_status === 'paid' ) {
                        $date_paid   += (int) $row->number_of_people;
                        $date_amount += (int) $row->amount;
                        if ( $row->cancelled_at === null ) {
                            $date_coming += (int) $row->number_of_people;
                        }
                    }
                }
            }

            echo '<h2 style="margin-top:28px;">' . esc_html( $date ) . '</h2>';
            printf(
                '<p><strong>来店予定席数:</strong> %d席 ／ <strong>決済済み席数:</strong> %d席 ／ <strong>売上:</strong> $%s</p>',
                $date_coming,
                $date_paid,
                esc_html( number_format( $date_amount ) )
            );

            foreach ( $slots as $slot_key => $rows ) {
                $slot_label  = KKPAY_SLOT_LABELS['ja'][ $slot_key ] ?? $slot_key;
                $slot_people = 0;
                $slot_amount = 0;

                echo '<h3>' . esc_html( $slot_label ) . '</h3>';
                echo '<table class="wp-list-table widefat striped">';
                echo '<thead><tr>'
                    . '<th>ID</th><th>名前</th><th>メール</th><th>席数</th><th>金額</th><th>決済ステータス</th><th>言語</th><th>作成日時</th><th>キャンセル日時</th>'
                    . '</tr></thead><tbody>';

                foreach ( $rows as $row ) {
                    $is_cancelled = $row->cancelled_at !== null;
                    if ( $row->payment_status === 'paid' && ! $is_cancelled ) {
                        $slot_people += (int) $row->number_of_people;
                        $slot_amount += (int) $row->amount;
                    }

                    echo '<tr>';
                    echo '<td>' . (int) $row->id . '</td>';
                    echo '<td>' . esc_html( $row->name ) . '</td>';
                    echo '<td>' . esc_html( $row->email ) . '</td>';
                    echo '<td>' . (int) $row->number_of_people . '席</td>';
                    echo '<td>$' . esc_html( number_format( (int) $row->amount ) ) . '</td>';
                    echo '<td>' . esc_html( $row->payment_status ) . '</td>';
                    echo '<td>' . esc_html( $row->language ) . '</td>';
                    echo '<td>' . esc_html( $row->created_at ) . '</td>';
                    echo '<td>' . esc_html( $row->cancelled_at ?? '―' ) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
                printf(
                    '<p style="text-align:right;"><strong>スロット合計:</strong> %d席 ／ $%s</p>',
                    $slot_people,
                    esc_html( number_format( $slot_amount ) )
                );
            }
        }
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

        $reserved = KKPAY_Reservation_Repository::sum_people_by_date_range( $from, $to );

        $slot_keys = array_keys( KKPAY_SLOT_TYPES );
        ?>
        <div id="kkpay-seat-capacity" style="margin-top:20px;">
            <p>
                各営業日・時間帯の予約可能席数を設定します。カレンダープラグインで営業日に設定されている日程のみ表示されます。<br>
                設定した席数はホールド作成時・予約確定時のチェックに即時反映されます。
            </p>
            <div style="display:flex;gap:8px;align-items:center;margin:16px 0;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:4px;">
                    一括設定する席数:
                    <input type="number" id="kkpay-bulk-cap-val" min="0" max="255"
                           value="<?php echo esc_attr( KKPAY_MAX_CAPACITY ); ?>"
                           style="width:72px;" />
                    席
                </label>
                <button type="button" class="button" id="kkpay-apply-bulk-cap">表示中の全スロットに適用</button>
                <button type="button" class="button button-primary" id="kkpay-save-cap">保存する</button>
                <span id="kkpay-cap-message" style="margin-left:8px;font-weight:bold;"></span>
            </div>
            <table class="widefat striped" style="table-layout:fixed;">
                <thead>
                    <tr>
                        <th style="width:105px;">日付</th>
                        <th style="width:36px;">曜日</th>
                        <?php foreach ( $slot_keys as $slot ) :
                            $label = KKPAY_SLOT_LABELS['ja'][ $slot ] ?? $slot;
                            ?>
                            <th style="font-size:11px;line-height:1.4;">
                                <?php echo esc_html( $slot ); ?><br>
                                <span style="font-weight:normal;color:#555;"><?php echo esc_html( $label ); ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $days_output = 0;
                    $dow_labels  = array( '日', '月', '火', '水', '木', '金', '土' );

                    for ( $i = 0; $i <= 90; $i++ ) :
                        $date     = $today->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
                        $calendar = KKPAY_Calendar_Repository::find_by_date( $date );

                        $open_slots = array();
                        if ( $calendar ) {
                            foreach ( KKPAY_SLOT_TYPES as $slot => $type ) {
                                if ( ( $type === 'lunch' && $calendar->lunch ) || ( $type === 'dinner' && $calendar->dinner ) ) {
                                    $open_slots[] = $slot;
                                }
                            }
                        }

                        if ( empty( $open_slots ) ) {
                            continue;
                        }
                        $days_output++;

                        $dow       = (int) ( new DateTimeImmutable( $date, $tz ) )->format( 'w' );
                        $dow_label = $dow_labels[ $dow ];
                        $dow_style = $dow === 0 ? 'color:#c00;font-weight:bold;' : ( $dow === 6 ? 'color:#00c;font-weight:bold;' : '' );
                        ?>
                        <tr class="kkpay-cap-row" data-date="<?php echo esc_attr( $date ); ?>">
                            <td><?php echo esc_html( $date ); ?></td>
                            <td style="text-align:center;<?php echo esc_attr( $dow_style ); ?>"><?php echo esc_html( $dow_label ); ?></td>
                            <?php foreach ( $slot_keys as $slot ) :
                                $is_open  = in_array( $slot, $open_slots, true );
                                $capacity = $saved[ $date ][ $slot ] ?? KKPAY_MAX_CAPACITY;
                                ?>
                                <td>
                                    <?php if ( $is_open ) : ?>
                                        <?php $current = $reserved[ $date ][ $slot ] ?? 0; ?>
                                        <input type="number" class="kkpay-cap-input"
                                               data-slot="<?php echo esc_attr( $slot ); ?>"
                                               min="0" max="255"
                                               value="<?php echo esc_attr( $capacity ); ?>"
                                               style="width:64px;" /> 席<br>
                                        <span style="font-size:11px;color:#555;">予約中: <?php echo $current; ?>席</span>
                                    <?php else : ?>
                                        <span style="color:#bbb;">定休</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                    <?php if ( $days_output === 0 ) : ?>
                        <tr>
                            <td colspan="<?php echo count( $slot_keys ) + 2; ?>" style="padding:16px;">
                                今後90日間に営業日が登録されていません。カレンダープラグインを確認してください。
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <script>
        jQuery(function ($) {
            $('#kkpay-apply-bulk-cap').on('click', function () {
                var cap = parseInt($('#kkpay-bulk-cap-val').val(), 10);
                if (isNaN(cap) || cap < 0) cap = <?php echo (int) KKPAY_MAX_CAPACITY; ?>;
                $('.kkpay-cap-row .kkpay-cap-input').val(cap);
            });

            $('#kkpay-save-cap').on('click', function () {
                var dates = [];
                $('.kkpay-cap-row').each(function () {
                    var $row  = $(this);
                    var slots = {};
                    $row.find('.kkpay-cap-input').each(function () {
                        slots[$(this).data('slot')] = parseInt($(this).val(), 10) || 0;
                    });
                    if (Object.keys(slots).length > 0) {
                        dates.push({ date: $row.data('date'), slots: slots });
                    }
                });

                var $msg = $('#kkpay-cap-message');
                $msg.css('color', '#555').text('保存中...');
                $.post(ajaxurl, {
                    action: 'kkpay_save_slot_capacity',
                    nonce:  '<?php echo esc_js( wp_create_nonce( 'kkpay_nonce' ) ); ?>',
                    dates:  JSON.stringify(dates)
                }, function (res) {
                    if (res.success) {
                        $msg.css('color', 'green').text('保存しました。');
                    } else {
                        var errMsg = res.data && res.data.message ? res.data.message : '保存に失敗しました。';
                        $msg.css('color', 'red').text(errMsg);
                    }
                }).fail(function () {
                    $msg.css('color', 'red').text('保存に失敗しました。通信エラーが発生しました。');
                });
            });
        });
        </script>
        <?php
    }

    private static function render_filter_form( $filter_date, $filter_slot ) {
        $csv_url = add_query_arg( array(
            'action'      => 'kkpay_export_csv',
            'filter_date' => $filter_date,
            'filter_slot' => $filter_slot,
            'nonce'       => wp_create_nonce( 'kkpay_export' ),
        ), admin_url( 'admin-ajax.php' ) );
        ?>
        <form method="get" style="margin:20px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <input type="hidden" name="page" value="kkpay-settings" />
            <label>日付
                <input type="date" name="filter_date" value="<?php echo esc_attr( $filter_date ); ?>" />
            </label>
            <label>スロット
                <select name="filter_slot">
                    <option value="">すべて</option>
                    <?php foreach ( array_keys( KKPAY_SLOT_TYPES ) as $slot ) : ?>
                        <option value="<?php echo esc_attr( $slot ); ?>" <?php selected( $filter_slot, $slot ); ?>>
                            <?php echo esc_html( KKPAY_SLOT_LABELS['ja'][ $slot ] ?? $slot ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="button">絞り込み</button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kkpay-settings' ) ); ?>" class="button">リセット</a>
            <a href="<?php echo esc_url( $csv_url ); ?>" class="button button-primary" style="margin-left:auto;">CSV出力</a>
        </form>
        <?php
    }
}
