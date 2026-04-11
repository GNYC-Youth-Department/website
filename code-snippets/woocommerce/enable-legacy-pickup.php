/**
 * Re-enable legacy local pickup shipping method
 * for use with classic shortcode checkout.
 */
add_filter( 'woocommerce_shipping_methods', function( $methods ) {
    $methods['legacy_local_pickup'] = 'WC_Shipping_Local_Pickup';
    return $methods;
});