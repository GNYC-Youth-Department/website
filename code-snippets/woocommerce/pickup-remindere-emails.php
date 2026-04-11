/**
 * Register cron schedules.
 */
add_filter( 'cron_schedules', 'add_pickup_cron_schedules' );
function add_pickup_cron_schedules( $schedules ) {
    $schedules['daily_8am'] = [
        'interval' => 86400,
        'display'  => 'Once Daily',
    ];
    return $schedules;
}

/**
 * Schedule cron jobs on init if not already scheduled.
 */
add_action( 'init', 'schedule_pickup_reminder_crons' );
function schedule_pickup_reminder_crons() {
    if ( ! wp_next_scheduled( 'send_pickup_day_before_reminders' ) ) {
        $timezone = new DateTimeZone( wp_timezone_string() );
        $now      = new DateTime( 'now', $timezone );
        $next_8am = new DateTime( 'today 08:00:00', $timezone );

        if ( $now > $next_8am ) {
            $next_8am->modify( '+1 day' );
        }

        wp_schedule_event(
            $next_8am->getTimestamp(),
            'daily',
            'send_pickup_day_before_reminders'
        );
    }

    if ( ! wp_next_scheduled( 'send_pickup_morning_reminders' ) ) {
        $timezone = new DateTimeZone( wp_timezone_string() );
        $now      = new DateTime( 'now', $timezone );
        $next_8am = new DateTime( 'today 08:00:00', $timezone );

        if ( $now > $next_8am ) {
            $next_8am->modify( '+1 day' );
        }

        wp_schedule_event(
            $next_8am->getTimestamp(),
            'daily',
            'send_pickup_morning_reminders'
        );
    }
}

/**
 * Get order details for email.
 */
function get_pickup_email_order_details( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return [];
    }

    $items = [];
    foreach ( $order->get_items() as $item ) {
        $product   = $item->get_product();
        $name      = $item->get_name();
        $qty       = $item->get_quantity();
        $subtotal  = wc_price( $item->get_subtotal() );
        $variation = [];

        if ( $product && $product->is_type( 'variation' ) ) {
            foreach ( $item->get_meta_data() as $meta ) {
                if ( strpos( $meta->key, 'attribute_' ) !== false ) {
                    $key         = str_replace( 'attribute_pa_', '', $meta->key );
                    $variation[] = ucfirst( $key ) . ': ' . ucfirst( $meta->value );
                }
            }
        }

        $items[] = [
            'name'      => $name,
            'qty'       => $qty,
            'subtotal'  => $subtotal,
            'variation' => implode( ', ', $variation ),
        ];
    }

    return [
        'order_number'  => $order->get_order_number(),
        'order_date'    => $order->get_date_created()->date_i18n( 'F j, Y' ),
        'order_total'   => wc_price( $order->get_total() ),
        'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'items'         => $items,
    ];
}

/**
 * Build reminder email HTML.
 */
function build_pickup_reminder_email( $booking, $order_details, $is_morning ) {
    $location_name    = get_the_title( $booking->location_id );
    $location_address = get_field( 'location_address', $booking->location_id );

    $date_obj     = DateTime::createFromFormat( 'Y-m-d', $booking->pickup_date );
    $date_display = $date_obj ? $date_obj->format( 'l, F j, Y' ) : $booking->pickup_date;

    $time_obj     = DateTime::createFromFormat( 'H:i', $booking->pickup_time );
    $time_display = $time_obj ? $time_obj->format( 'g:i A' ) : $booking->pickup_time;

    $subject = $is_morning
        ? 'Reminder: Your pickup is TODAY at ' . $time_display
        : 'Reminder: Your pickup is TOMORROW — ' . $date_display;

    $heading = $is_morning
        ? 'Your Pickup is Today!'
        : 'Your Pickup is Tomorrow!';

    $greeting_line = $is_morning
        ? 'This is a friendly reminder that your order is ready for pickup <strong>today</strong>.'
        : 'This is a friendly reminder that your order is scheduled for pickup <strong>tomorrow</strong>.';

    // Build order items rows
    $items_html = '';
    foreach ( $order_details['items'] as $item ) {
        $variation  = ! empty( $item['variation'] )
            ? '<br><span style="color:#888; font-size:0.85em;">' . esc_html( $item['variation'] ) . '</span>'
            : '';
        $items_html .= '
            <tr>
                <td style="padding: 8px 10px; border-bottom: 1px solid #eee;">'
                    . esc_html( $item['name'] ) . $variation .
                '</td>
                <td style="padding: 8px 10px; border-bottom: 1px solid #eee; text-align: center;">'
                    . esc_html( $item['qty'] ) .
                '</td>
                <td style="padding: 8px 10px; border-bottom: 1px solid #eee; text-align: right;">'
                    . $item['subtotal'] .
                '</td>
            </tr>';
    }

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; border: 1px solid #eee; padding: 20px; border-radius: 8px; }
        .header { border-bottom: 2px solid #f4f4f4; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .logo { max-width: 150px; height: auto; margin-bottom: 15px; display: inline-block; }
        .header h1 { font-size: 1.4em; margin: 0; color: #333; line-height: 1.2; }
        .details-box { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .details-row { margin-bottom: 8px; font-size: 0.95em; }
        .label { font-weight: bold; color: #555; display: inline-block; width: 140px; }
        .order-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 0.95em; }
        .order-table th { background-color: #f4f4f4; padding: 8px 10px; text-align: left; font-size: 0.9em; color: #555; }
        .order-table th:last-child { text-align: right; }
        .order-table th:nth-child(2) { text-align: center; }
        .order-total-row td { padding: 8px 10px; font-weight: bold; text-align: right; border-top: 2px solid #eee; }
        .footer { font-size: 0.8em; color: #888; margin-top: 40px; border-top: 1px solid #eee; padding-top: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">

        <!-- Header -->
        <div class="header">
            <img src="https://gnycyouth.org/wp-content/uploads/2026/03/logo-300x251.png"
                 alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"
                 class="logo">
            <h1>' . esc_html( $heading ) . '</h1>
        </div>

        <p>Hi <strong>' . esc_html( $order_details['customer_name'] ) . '</strong>,</p>
        <p>' . $greeting_line . ' Please review your pickup details below.</p>

        <!-- Pickup Details -->
        <div class="details-box">
            <div class="details-row">
                <span class="label">Location:</span>
                ' . esc_html( $location_name ) . '
            </div>
            <div class="details-row">
                <span class="label">Address:</span>
                ' . esc_html( $location_address ) . '
            </div>
            <div class="details-row">
                <span class="label">Date:</span>
                ' . esc_html( $date_display ) . '
            </div>
            <div class="details-row">
                <span class="label">Time:</span>
                ' . esc_html( $time_display ) . '
            </div>
            <div class="details-row">
                <span class="label">Order #:</span>
                ' . esc_html( $order_details['order_number'] ) . '
            </div>
            <div class="details-row">
                <span class="label">Order Date:</span>
                ' . esc_html( $order_details['order_date'] ) . '
            </div>
        </div>

        <!-- Order Summary -->
        <p><strong>Order Summary</strong></p>
        <table class="order-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                ' . $items_html . '
            </tbody>
            <tfoot>
                <tr class="order-total-row">
                    <td colspan="2" style="padding: 8px 10px; text-align: right; border-top: 2px solid #eee; font-weight: bold;">
                        Order Total:
                    </td>
                    <td style="padding: 8px 10px; text-align: right; border-top: 2px solid #eee; font-weight: bold;">
                        ' . $order_details['order_total'] . '
                    </td>
                </tr>
            </tfoot>
        </table>

        <p>Please bring your order confirmation when picking up your items. If you have any questions, feel free to contact us.</p>
        <p>We look forward to seeing you!</p>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0;">' . esc_html( get_bloginfo( 'name' ) ) . '</p>
            <p style="margin: 5px 0 0 0;">
                <a href="' . esc_url( get_bloginfo( 'url' ) ) . '" style="color: #888;">
                    ' . esc_html( get_bloginfo( 'url' ) ) . '
                </a>
            </p>
            <p style="margin: 5px 0 0 0;">Sent via ' . esc_html( get_bloginfo( 'name' ) ) . ' Store</p>
        </div>

    </div>
</body>
</html>';

    return [
        'subject' => $subject,
        'html'    => $html,
    ];
}

/**
 * Send pickup reminder emails.
 */
function send_pickup_reminders( $is_morning = false ) {
    global $wpdb;

    $table    = $wpdb->prefix . 'pickup_bookings';
    $timezone = new DateTimeZone( wp_timezone_string() );

    if ( $is_morning ) {
        $target_date = ( new DateTime( 'now', $timezone ) )->format( 'Y-m-d' );
        $column      = 'reminder_morning';
    } else {
        $target_date = ( new DateTime( 'tomorrow', $timezone ) )->format( 'Y-m-d' );
        $column      = 'reminder_day_before';
    }

    $bookings = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table WHERE pickup_date = %s AND $column = 0",
        $target_date
    ));

    if ( empty( $bookings ) ) {
        return;
    }

    foreach ( $bookings as $booking ) {
        $order_details = get_pickup_email_order_details( $booking->order_id );

        if ( empty( $order_details ) ) {
            continue;
        }

        $email = build_pickup_reminder_email( $booking, $order_details, $is_morning );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <store@gnycyouth.org>',
            'Reply-To: ' . get_bloginfo( 'name' ) . ' <store@gnycyouth.org>',
        ];

        $sent = wp_mail(
            $booking->customer_email,
            $email['subject'],
            $email['html'],
            $headers
        );

        if ( $sent ) {
            $wpdb->update(
                $table,
                [ $column => 1 ],
                [ 'id' => $booking->id ]
            );
        }
    }
}

/**
 * Cron hook — day before reminder.
 */
add_action( 'send_pickup_day_before_reminders', function() {
    send_pickup_reminders( false );
});

/**
 * Cron hook — morning of reminder.
 */
add_action( 'send_pickup_morning_reminders', function() {
    send_pickup_reminders( true );
});