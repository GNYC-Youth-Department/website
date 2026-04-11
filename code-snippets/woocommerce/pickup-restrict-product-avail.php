/**
 * Restrict product availability using ACF date/time fields.
 * Supports both recurring annual and one-time products.
 */
function is_product_in_season( $product_id ) {
    $start   = get_field( 'availability_start_date', $product_id );
    $end     = get_field( 'availability_end_date', $product_id );
    $expires = get_field( 'expires_after_end_date', $product_id );

    if ( ! $start ) {
        return true;
    }

    $now        = new DateTime( 'now', new DateTimeZone( wp_timezone_string() ) );
    $start_dt   = DateTime::createFromFormat( 'Y-m-d H:i:s', $start, new DateTimeZone( wp_timezone_string() ) );

    // One-time product — compare full date and time
    if ( $expires ) {
        if ( ! $end ) {
            return $now >= $start_dt;
        }

        $end_dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $end, new DateTimeZone( wp_timezone_string() ) );
        return $now >= $start_dt && $now <= $end_dt;
    }

    // Recurring annual product — compare month/day/time only
    $now_md    = intval( $now->format( 'md' ) );
    $start_md  = intval( $start_dt->format( 'md' ) );

    if ( ! $end ) {
        if ( $now_md == $start_md ) {
            // Same day — check time too
            return $now >= $start_dt;
        }
        return $now_md >= $start_md;
    }

    $end_dt   = DateTime::createFromFormat( 'Y-m-d H:i:s', $end, new DateTimeZone( wp_timezone_string() ) );
    $end_md   = intval( $end_dt->format( 'md' ) );
    $now_time = intval( $now->format( 'Hi' ) );

    if ( $start_md <= $end_md ) {
        if ( $now_md == $start_md ) {
            return $now >= $start_dt && $now_md <= $end_md;
        }
        if ( $now_md == $end_md ) {
            return $now_md >= $start_md && $now <= $end_dt;
        }
        return $now_md >= $start_md && $now_md <= $end_md;
    } else {
        return $now_md >= $start_md || $now_md <= $end_md;
    }
}

/**
 * Helper to build the unavailability message.
 */
function get_seasonal_message( $product_id ) {
    $expires = get_field( 'expires_after_end_date', $product_id );
    $start   = get_field( 'availability_start_date', $product_id );
    $end     = get_field( 'availability_end_date', $product_id );

    if ( $expires ) {
        $date = DateTime::createFromFormat( 'Y-m-d H:i:s', $end, new DateTimeZone( wp_timezone_string() ) );
        return 'This product is no longer available. Sales ended on <strong>'
            . esc_html( $date->format( 'F j, Y \a\t g:i A' ) ) . '</strong>.';
    } else {
        $date = DateTime::createFromFormat( 'Y-m-d H:i:s', $start, new DateTimeZone( wp_timezone_string() ) );
        return 'This product is not currently available. It returns on <strong>'
            . esc_html( $date->format( 'F j \a\t g:i A' ) ) . '</strong>.';
    }
}

// Remove "Add to Cart" button on single product page when out of season (standard WooCommerce templates)
add_action( 'woocommerce_single_product_summary', 'seasonal_product_purchase_block', 25 );
function seasonal_product_purchase_block() {
    global $product;
    $product_id = $product->get_id();

    if ( ! is_product_in_season( $product_id ) ) {
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        echo '<p class="seasonal-unavailable">' . get_seasonal_message( $product_id ) . '</p>';
    }
}

// Hide Elementor Add to Cart widget and show message (Elementor Pro templates)
add_action( 'wp_footer', 'seasonal_elementor_add_to_cart_block' );
function seasonal_elementor_add_to_cart_block() {
    if ( ! is_product() ) {
        return;
    }

    global $product;
    $product_id = $product->get_id();

    if ( is_product_in_season( $product_id ) ) {
        return;
    }

    $message = get_seasonal_message( $product_id );

    ?>
    <style>
        .elementor-widget-woocommerce-product-add-to-cart {
            display: none !important;
        }
        .seasonal-unavailable-elementor {
            margin: 10px 0;
        }
    </style>
    <script>
        document.addEventListener( 'DOMContentLoaded', function() {
            var addToCart = document.querySelector( '.elementor-widget-woocommerce-product-add-to-cart' );
            if ( addToCart ) {
                var message = document.createElement( 'p' );
                message.className = 'seasonal-unavailable-elementor';
                message.innerHTML = '<?php echo $message; ?>';
                addToCart.parentNode.insertBefore( message, addToCart );
                addToCart.style.display = 'none';
            }
        });
    </script>
    <?php
}

// Block add-to-cart via direct URL/AJAX when out of season
add_filter( 'woocommerce_is_purchasable', 'seasonal_product_purchasable', 10, 2 );
function seasonal_product_purchasable( $purchasable, $product ) {
    if ( $purchasable && ! is_product_in_season( $product->get_id() ) ) {
        return false;
    }
    return $purchasable;
}

// Block add-to-cart at validation level (catches Elementor Pro's cart bypass)
add_filter( 'woocommerce_add_to_cart_validation', 'seasonal_add_to_cart_validation', 10, 2 );
function seasonal_add_to_cart_validation( $passed, $product_id ) {
    if ( ! is_product_in_season( $product_id ) ) {
        wc_add_notice( 'This product is not currently available.', 'error' );
        return false;
    }
    return $passed;
}