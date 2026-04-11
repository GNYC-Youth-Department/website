/**
 * Force local pickup as the only shipping method
 * when a pickup-only product is in the cart.
 * Hide local pickup entirely when no pickup-only products are in cart.
 */
add_filter( 'woocommerce_package_rates', 'enforce_pickup_only_shipping', 10, 2 );
function enforce_pickup_only_shipping( $rates, $package ) {
    $has_pickup_only = false;

    foreach ( $package['contents'] as $cart_item ) {
        $product_id  = $cart_item['product_id'];
        $pickup_only = get_field( 'pickup_only', $product_id );

        if ( $pickup_only ) {
            $has_pickup_only = true;
            break;
        }
    }

    $pickup_rates  = [];
    $regular_rates = [];

    foreach ( $rates as $rate_id => $rate ) {
        if ( strpos( $rate_id, 'local_pickup' ) !== false ) {
            $pickup_rates[ $rate_id ] = $rate;
        } else {
            $regular_rates[ $rate_id ] = $rate;
        }
    }

    if ( $has_pickup_only ) {
        // Pickup-only product in cart — show ONLY local pickup
        return ! empty( $pickup_rates ) ? $pickup_rates : $rates;
    } else {
        // No pickup-only products — hide local pickup, show regular methods only
        return ! empty( $regular_rates ) ? $regular_rates : $rates;
    }
}

/**
 * Show a notice on cart when a pickup-only product is present.
 */
add_action( 'woocommerce_before_cart', 'pickup_only_cart_notice' );
function pickup_only_cart_notice() {
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $product_id  = $cart_item['product_id'];
        $pickup_only = get_field( 'pickup_only', $product_id );

        if ( $pickup_only ) {
            wc_add_notice(
                'Your cart contains a pickup-only item. Only local pickup is available for this order.',
                'notice'
            );
            break;
        }
    }
}