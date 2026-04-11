/**
 * Phase 2d — Validate, save, and display pickup selections on orders.
 * Single pickup selection applies to entire order.
 */

/**
 * Validate pickup fields before order is placed.
 */
add_action( 'woocommerce_checkout_process', 'validate_pickup_selections' );
function validate_pickup_selections() {
    $items = get_pickup_cart_items();

    if ( empty( $items ) ) {
        return;
    }

    $chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
    $is_local_pickup = false;
    foreach ( $chosen_methods as $method ) {
        if ( strpos( $method, 'local_pickup' ) !== false ) {
            $is_local_pickup = true;
            break;
        }
    }

    if ( ! $is_local_pickup ) {
        return;
    }

    $location = isset( $_POST['pickup_location_order'] )
        ? sanitize_text_field( $_POST['pickup_location_order'] )
        : '';
    $date     = isset( $_POST['pickup_date_order'] )
        ? sanitize_text_field( $_POST['pickup_date_order'] )
        : '';
    $time     = isset( $_POST['pickup_time_order'] )
        ? sanitize_text_field( $_POST['pickup_time_order'] )
        : '';

    if ( empty( $location ) ) {
        wc_add_notice( 'Please select a pickup location.', 'error' );
    }
    if ( empty( $date ) ) {
        wc_add_notice( 'Please select a pickup date.', 'error' );
    }
    if ( empty( $time ) ) {
        wc_add_notice( 'Please select a pickup time.', 'error' );
    }
}

/**
 * Save pickup selections to order meta and booking table.
 */
add_action( 'woocommerce_checkout_order_processed', 'save_pickup_selections', 10, 3 );
function save_pickup_selections( $order_id, $posted_data, $order ) {
    $items = get_pickup_cart_items();

    if ( empty( $items ) ) {
        return;
    }

    $chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
    $is_local_pickup = false;
    foreach ( $chosen_methods as $method ) {
        if ( strpos( $method, 'local_pickup' ) !== false ) {
            $is_local_pickup = true;
            break;
        }
    }

    if ( ! $is_local_pickup ) {
        return;
    }

    $location_id = isset( $_POST['pickup_location_order'] )
        ? intval( $_POST['pickup_location_order'] )
        : 0;
    $date        = isset( $_POST['pickup_date_order'] )
        ? sanitize_text_field( $_POST['pickup_date_order'] )
        : '';
    $time        = isset( $_POST['pickup_time_order'] )
        ? sanitize_text_field( $_POST['pickup_time_order'] )
        : '';

    if ( ! $location_id || ! $date || ! $time ) {
        return;
    }

    $location_name    = get_the_title( $location_id );
    $location_address = get_field( 'location_address', $location_id );
    $date_obj         = DateTime::createFromFormat( 'Ymd', $date );
    $date_display     = $date_obj ? $date_obj->format( 'l, F j, Y' ) : $date;
    $time_obj         = DateTime::createFromFormat( 'H:i', $time );
    $time_display     = $time_obj ? $time_obj->format( 'g:i A' ) : $time;
    $customer_email   = $order->get_billing_email();

    global $wpdb;
    $table = $wpdb->prefix . 'pickup_bookings';

    // Save one booking row per pickup item
    foreach ( $items as $item ) {
        $wpdb->insert( $table, [
            'order_id'       => $order_id,
            'product_id'     => $item['product_id'],
            'location_id'    => $location_id,
            'pickup_date'    => $date_obj ? $date_obj->format( 'Y-m-d' ) : $date,
            'pickup_time'    => $time,
            'customer_email' => $customer_email,
        ]);
    }

    // Save single pickup selection to order meta
    $pickup_data = [
        'location_id'      => $location_id,
        'location_name'    => $location_name,
        'location_address' => $location_address,
        'date'             => $date,
        'date_display'     => $date_display,
        'time'             => $time,
        'time_display'     => $time_display,
        'products'         => array_column( $items, 'name' ),
    ];

    $order->update_meta_data( '_pickup_selections', $pickup_data );
    $order->save();
}

/**
 * Normalize pickup meta to ensure all expected keys exist.
 * Provides fallbacks for orders saved before display keys were introduced.
 *
 * @param array $pickup Raw pickup meta array.
 * @return array Normalized pickup array.
 */
function normalize_pickup_meta( $pickup ) {
    return wp_parse_args( $pickup, [
        'products'         => [],
        'location_name'    => '',
        'location_address' => '',
        'date_display'     => $pickup['date'] ?? '',
        'time_display'     => $pickup['time'] ?? '',
    ]);
}

/**
 * Display pickup details in order admin.
 */
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'display_pickup_in_order_admin', 10, 1 );
function display_pickup_in_order_admin( $order ) {
    $pickup = $order->get_meta( '_pickup_selections' );

    if ( empty( $pickup ) ) {
        return;
    }

    $pickup = normalize_pickup_meta( $pickup );

    echo '<div class="pickup-order-details" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #e5e5e5;">';
    echo '<h4 style="margin-bottom: 10px;">Pickup Details</h4>';

    if ( ! empty( $pickup['products'] ) ) {
        echo '<strong>Products:</strong> ' . esc_html( implode( ', ', $pickup['products'] ) ) . '<br>';
    }

    echo '<strong>Location:</strong> ' . esc_html( $pickup['location_name'] ) . '<br>';
    echo '<strong>Address:</strong> '  . esc_html( $pickup['location_address'] ) . '<br>';
    echo '<strong>Date:</strong> '     . esc_html( $pickup['date_display'] ) . '<br>';
    echo '<strong>Time:</strong> '     . esc_html( $pickup['time_display'] ) . '<br>';
    echo '</div>';
}

/**
 * Display pickup details in order confirmation email.
 */
add_action( 'woocommerce_email_order_details', 'display_pickup_in_email', 5, 4 );
function display_pickup_in_email( $order, $sent_to_admin, $plain_text, $email ) {
    $pickup = $order->get_meta( '_pickup_selections' );

    if ( empty( $pickup ) ) {
        return;
    }

    $pickup = normalize_pickup_meta( $pickup );

    if ( $plain_text ) {
        echo "\n\nPICKUP DETAILS\n";
        echo "==============\n";
        if ( ! empty( $pickup['products'] ) ) {
            echo 'Products: ' . implode( ', ', $pickup['products'] ) . "\n";
        }
        echo 'Location: ' . $pickup['location_name'] . "\n";
        echo 'Address: '  . $pickup['location_address'] . "\n";
        echo 'Date: '     . $pickup['date_display'] . "\n";
        echo 'Time: '     . $pickup['time_display'] . "\n";
    } else {
        echo '<div style="margin-bottom: 40px;">';
        echo '<h2 style="color: #96588a;">Pickup Details</h2>';
        echo '<table cellspacing="0" cellpadding="6" style="width:100%; border: 1px solid #e5e5e5;">';

        if ( ! empty( $pickup['products'] ) ) {
            echo '<tr>';
            echo '<th style="text-align:left; border:1px solid #e5e5e5; padding:8px;">Products</th>';
            echo '<td style="border:1px solid #e5e5e5; padding:8px;">' . esc_html( implode( ', ', $pickup['products'] ) ) . '</td>';
            echo '</tr>';
        }

        echo '<tr>';
        echo '<th style="text-align:left; border:1px solid #e5e5e5; padding:8px;">Location</th>';
        echo '<td style="border:1px solid #e5e5e5; padding:8px;">' . esc_html( $pickup['location_name'] ) . '<br><small>' . esc_html( $pickup['location_address'] ) . '</small></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th style="text-align:left; border:1px solid #e5e5e5; padding:8px;">Date</th>';
        echo '<td style="border:1px solid #e5e5e5; padding:8px;">' . esc_html( $pickup['date_display'] ) . '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th style="text-align:left; border:1px solid #e5e5e5; padding:8px;">Time</th>';
        echo '<td style="border:1px solid #e5e5e5; padding:8px;">' . esc_html( $pickup['time_display'] ) . '</td>';
        echo '</tr>';

        echo '</table>';
        echo '</div>';
    }
}

/**
 * Display pickup details on customer order page.
 */
add_action( 'woocommerce_order_details_after_order_table', 'display_pickup_on_order_page', 10, 1 );
function display_pickup_on_order_page( $order ) {
    $pickup = $order->get_meta( '_pickup_selections' );

    if ( empty( $pickup ) ) {
        return;
    }

    $pickup = normalize_pickup_meta( $pickup );

    echo '<section class="woocommerce-pickup-details">';
    echo '<h2 style="margin-top: 30px;">Pickup Details</h2>';
    echo '<table class="woocommerce-table" cellspacing="0">';

    if ( ! empty( $pickup['products'] ) ) {
        echo '<tr><th>Products</th><td>' . esc_html( implode( ', ', $pickup['products'] ) ) . '</td></tr>';
    }

    echo '<tr><th>Location</th><td>' . esc_html( $pickup['location_name'] ) . '<br><small>' . esc_html( $pickup['location_address'] ) . '</small></td></tr>';
    echo '<tr><th>Date</th><td>'     . esc_html( $pickup['date_display'] ) . '</td></tr>';
    echo '<tr><th>Time</th><td>'     . esc_html( $pickup['time_display'] ) . '</td></tr>';

    echo '</table>';
    echo '</section>';
}

/**
 * Set store email and reply-to for all outgoing WooCommerce emails.
 */
add_filter( 'woocommerce_email_from_address', 'set_pickup_from_email' );
function set_pickup_from_email( $email ) {
    return 'store@gnycyouth.org';
}

add_filter( 'woocommerce_email_from_name', 'set_pickup_from_name' );
function set_pickup_from_name( $name ) {
    return get_bloginfo( 'name' );
}

add_filter( 'wp_mail_from', 'set_pickup_wp_mail_from' );
function set_pickup_wp_mail_from( $email ) {
    return 'store@gnycyouth.org';
}

add_filter( 'wp_mail_from_name', 'set_pickup_wp_mail_from_name' );
function set_pickup_wp_mail_from_name( $name ) {
    return get_bloginfo( 'name' );
}

/**
 * Add Reply-To header to all outgoing emails.
 */
add_filter( 'wp_mail', 'add_pickup_reply_to_header' );
function add_pickup_reply_to_header( $args ) {
    $reply_to = get_bloginfo( 'name' ) . ' <store@gnycyouth.org>';

    if ( is_array( $args['headers'] ) ) {
        $args['headers'][] = 'Reply-To: ' . $reply_to;
    } else {
        $args['headers'] .= "\r\nReply-To: " . $reply_to;
    }

    return $args;
}