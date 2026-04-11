/**
 * Create custom pickup bookings table on init.
 */
function create_pickup_bookings_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'pickup_bookings';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT(20) UNSIGNED NOT NULL,
        product_id BIGINT(20) UNSIGNED NOT NULL,
        location_id BIGINT(20) UNSIGNED NOT NULL,
        pickup_date DATE NOT NULL,
        pickup_time VARCHAR(10) NOT NULL,
        customer_email VARCHAR(255) NOT NULL,
        reminder_day_before TINYINT(1) DEFAULT 0,
        reminder_morning TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY product_id (product_id),
        KEY location_id (location_id),
        KEY pickup_date (pickup_date)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
add_action( 'init', 'create_pickup_bookings_table' );