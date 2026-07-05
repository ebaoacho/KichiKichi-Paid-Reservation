<?php
/**
 * Replace the WordPress /premium-guide/ page body with [kkpay_premium_guide].
 *
 * Usage:
 *   php tools/kkpay-fix-premium-guide-page.php /path/to/wordpress/wp-load.php
 *
 * The previous page body is saved to _kkpay_premium_guide_previous_content.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

if ( empty( $argv[1] ) || ! is_readable( $argv[1] ) ) {
    fwrite( STDERR, "Usage: php tools/kkpay-fix-premium-guide-page.php /path/to/wordpress/wp-load.php\n" );
    exit( 1 );
}

require_once $argv[1];

$content = '[kkpay_premium_guide]';
$page    = get_page_by_path( 'premium-guide', OBJECT, 'page' );

if ( ! $page ) {
    $page_id = wp_insert_post( array(
        'post_title'   => 'premium-guide',
        'post_name'    => 'premium-guide',
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_content' => $content,
    ), true );

    if ( is_wp_error( $page_id ) ) {
        fwrite( STDERR, $page_id->get_error_message() . "\n" );
        exit( 1 );
    }

    echo "Created /premium-guide/ page. ID={$page_id}\n";
    exit( 0 );
}

if ( trim( $page->post_content ) === $content ) {
    echo "/premium-guide/ already uses {$content}. ID={$page->ID}\n";
    exit( 0 );
}

update_post_meta( $page->ID, '_kkpay_premium_guide_previous_content', $page->post_content );

$updated = wp_update_post( array(
    'ID'           => $page->ID,
    'post_content' => $content,
), true );

if ( is_wp_error( $updated ) ) {
    fwrite( STDERR, $updated->get_error_message() . "\n" );
    exit( 1 );
}

echo "Updated /premium-guide/ page. ID={$page->ID}\n";
echo "Previous content saved in _kkpay_premium_guide_previous_content.\n";
exit( 0 );

