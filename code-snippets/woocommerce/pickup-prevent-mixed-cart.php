/**
 * Prevent mixing pickup-only and regular products in the same cart.
 */
add_filter( 'woocommerce_add_to_cart_validation', 'prevent_mixed_cart', 10, 2 );
function prevent_mixed_cart( $passed, $product_id ) {
    if ( ! WC()->cart || WC()->cart->is_empty() ) {
        return $passed;
    }

    $adding_pickup_only = get_field( 'pickup_only', $product_id );
    $cart_has_pickup_only   = false;
    $cart_has_regular       = false;

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $cart_product_id = $cart_item['product_id'];
        $is_pickup_only  = get_field( 'pickup_only', $cart_product_id );

        if ( $is_pickup_only ) {
            $cart_has_pickup_only = true;
        } else {
            $cart_has_regular = true;
        }
    }

    // Adding pickup-only to a cart with regular products
    if ( $adding_pickup_only && $cart_has_regular ) {
        wc_add_notice(
            'This item is available for local pickup only and cannot be purchased together with regular shipping items. Please complete your current order first or <a href="' . wc_get_cart_url() . '">clear your cart</a>.',
            'error'
        );
        return false;
    }

    // Adding regular product to a cart with pickup-only products
    if ( ! $adding_pickup_only && $cart_has_pickup_only ) {
        wc_add_notice(
            'Your cart contains a pickup-only item. Regular shipping items cannot be added. Please complete your current order first or <a href="' . wc_get_cart_url() . '">clear your cart</a>.',
            'error'
        );
        return false;
    }

    return $passed;
}