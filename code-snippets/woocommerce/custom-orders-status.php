/**
 * Register custom order status: Ready for Pickup
 */

// Register the status
add_action( 'init', 'register_ready_for_pickup_status' );
function register_ready_for_pickup_status() {
    register_post_status( 'wc-ready-pickup', [
        'label'                     => 'Ready for Pickup',
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop(
            'Ready for Pickup <span class="count">(%s)</span>',
            'Ready for Pickup <span class="count">(%s)</span>'
        ),
    ]);
}

// Add to WooCommerce order statuses list
add_filter( 'wc_order_statuses', 'add_ready_for_pickup_to_order_statuses' );
function add_ready_for_pickup_to_order_statuses( $order_statuses ) {
    // Insert after 'Processing'
    $new_statuses = [];
    foreach ( $order_statuses as $key => $status ) {
        $new_statuses[ $key ] = $status;
        if ( $key === 'wc-processing' ) {
            $new_statuses['wc-ready-pickup'] = 'Ready for Pickup';
        }
    }
    return $new_statuses;
}

// Add to HPOS order statuses
add_filter( 'woocommerce_register_shop_order_post_statuses', 'add_ready_pickup_to_hpos_statuses' );
function add_ready_pickup_to_hpos_statuses( $statuses ) {
    $statuses['wc-ready-pickup'] = [
        'label'                     => 'Ready for Pickup',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'Ready for Pickup <span class="count">(%s)</span>',
            'Ready for Pickup <span class="count">(%s)</span>'
        ),
    ];
    return $statuses;
}

// Add color styling for the status in order admin
add_action( 'admin_head', 'ready_for_pickup_status_style' );
function ready_for_pickup_status_style() {
    echo '<style>
        .order-status.status-ready-pickup {
            background: #c8d7e1 !important;
            color: #2e4453 !important;
        }
        mark.order-status.status-ready-pickup {
            background: #c8d7e1 !important;
            color: #2e4453 !important;
        }
    </style>';
}

// Allow the status to be set from the order admin dropdown
add_filter( 'woocommerce_order_is_paid_statuses', 'ready_pickup_is_paid_status' );
function ready_pickup_is_paid_status( $statuses ) {
    $statuses[] = 'ready-pickup';
    return $statuses;
}

// Send email notification to customer when status changes to Ready for Pickup
add_action( 'woocommerce_order_status_ready-pickup', 'send_ready_for_pickup_email', 10, 2 );
function send_ready_for_pickup_email( $order_id, $order ) {
    if ( ! $order ) {
        $order = wc_get_order( $order_id );
    }

    if ( ! $order ) {
        return;
    }

    $pickup = $order->get_meta( '_pickup_selections' );

    // Only send for pickup orders
    if ( empty( $pickup ) ) {
        return;
    }

    $customer_email   = $order->get_billing_email();
    $customer_name    = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $location_name    = $pickup['location_name'] ?? '';
    $location_address = $pickup['location_address'] ?? '';
    $date_display     = $pickup['date_display'] ?? '';
    $time_display     = $pickup['time_display'] ?? '';
    $order_number     = $order->get_order_number();

    $subject = 'Your order #' . $order_number . ' is ready for pickup!';

    $html = '
    <!DOCTYPE html>
    <html>
    <body style="margin:0; padding:0; background:#f7f7f7; font-family: Arial, sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f7; padding:40px 0;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff; border:1px solid #e5e5e5;">

                        <!-- Header -->
                        <tr>
                            <td style="background:#96588a; padding:30px; text-align:center;">
                                <h1 style="color:#fff; margin:0; font-size:24px;">Your Order is Ready!</h1>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style="padding:30px;">
                                <p>Hi <strong>' . esc_html( $customer_name ) . '</strong>,</p>
                                <p>Great news! Your order <strong>#' . esc_html( $order_number ) . '</strong> is ready for pickup.</p>

                                <!-- Pickup Details -->
                                <table width="100%" cellpadding="0" cellspacing="0"
                                       style="background:#f8f8f8; border:1px solid #e5e5e5; margin:20px 0;">
                                    <tr>
                                        <td style="padding:15px;">
                                            <h3 style="margin:0 0 15px 0; color:#96588a;">Pickup Details</h3>
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td style="padding:5px 0; width:120px;"><strong>Location:</strong></td>
                                                    <td style="padding:5px 0;">' . esc_html( $location_name ) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:5px 0;"><strong>Address:</strong></td>
                                                    <td style="padding:5px 0;">' . esc_html( $location_address ) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:5px 0;"><strong>Date:</strong></td>
                                                    <td style="padding:5px 0;">' . esc_html( $date_display ) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:5px 0;"><strong>Time:</strong></td>
                                                    <td style="padding:5px 0;">' . esc_html( $time_display ) . '</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <p>Please bring your order confirmation when picking up your items.</p>
                                <p>We look forward to seeing you!</p>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background:#f8f8f8; padding:20px; text-align:center;
                                       border-top:1px solid #e5e5e5; color:#666; font-size:12px;">
                                <p style="margin:0;">' . get_bloginfo( 'name' ) . '</p>
                                <p style="margin:5px 0 0 0;">' . get_bloginfo( 'url' ) . '</p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo( 'name' ) . ' <store@gnycyouth.org>',
        'Reply-To: ' . get_bloginfo( 'name' ) . ' <store@gnycyouth.org>',
    ];

    wp_mail( $customer_email, $subject, $html, $headers );
}