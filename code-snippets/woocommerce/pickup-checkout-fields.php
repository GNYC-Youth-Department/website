/**
 * Injects pickup selection UI into checkout via wp_footer
 * Single pickup selection applies to entire order.
 */

add_action( 'wp_enqueue_scripts', 'enqueue_pickup_checkout_scripts' );
function enqueue_pickup_checkout_scripts() {
    if ( ! is_checkout() ) {
        return;
    }

    wp_enqueue_script(
        'pickup-checkout',
        get_stylesheet_directory_uri() . '/js/pickup-checkout.js',
        [ 'jquery' ],
        '1.0.3',
        true
    );

    wp_localize_script( 'pickup-checkout', 'pickupData', [
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'pickup_nonce' ),
        'cartItems' => get_pickup_cart_items(),
    ]);
}

/**
 * Get cart items that have pickup configured.
 */
function get_pickup_cart_items() {
    $items = [];

    if ( ! WC()->cart ) {
        return $items;
    }

    foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
        $product_id = $cart_item['product_id'];
        $locations  = get_field( 'available_pickup_locations', $product_id );

        if ( empty( $locations ) ) {
            continue;
        }

        $location_options = [];
        foreach ( $locations as $location ) {
            $location_options[] = [
                'id'      => $location->ID,
                'name'    => $location->post_title,
                'address' => get_field( 'location_address', $location->ID ),
            ];
        }

        $variation_label = '';
        if ( ! empty( $cart_item['variation'] ) ) {
            $parts = [];
            foreach ( $cart_item['variation'] as $key => $value ) {
                $parts[] = ucfirst( str_replace( 'attribute_pa_', '', $key ) ) . ': ' . ucfirst( $value );
            }
            $variation_label = ' (' . implode( ', ', $parts ) . ')';
        }

        $items[] = [
            'cart_key'     => $cart_key,
            'product_id'   => $product_id,
            'variation_id' => $cart_item['variation_id'] ?? 0,
            'name'         => get_the_title( $product_id ) . $variation_label,
            'locations'    => $location_options,
        ];
    }

    return $items;
}

/**
 * Get the intersection of locations available across ALL pickup items in cart.
 * Only locations available for every pickup item are shown.
 */
function get_shared_pickup_locations() {
    $items = get_pickup_cart_items();

    if ( empty( $items ) ) {
        return [];
    }

    // Start with locations from first item
    $shared_ids = array_column( $items[0]['locations'], 'id' );

    // Intersect with each subsequent item
    foreach ( $items as $item ) {
        $item_location_ids = array_column( $item['locations'], 'id' );
        $shared_ids        = array_intersect( $shared_ids, $item_location_ids );
    }

    if ( empty( $shared_ids ) ) {
        return [];
    }

    // Build full location data for shared locations
    $locations = [];
    foreach ( $items[0]['locations'] as $location ) {
        if ( in_array( $location['id'], $shared_ids ) ) {
            $locations[] = $location;
        }
    }

    return $locations;
}

/**
 * Get the product ID to use for date range filtering.
 * If multiple products have date ranges, use the most restrictive window.
 */
function get_combined_pickup_date_range() {
    $items      = get_pickup_cart_items();
    $start_date = null;
    $end_date   = null;

    foreach ( $items as $item ) {
        $product_id   = $item['product_id'];
        $product_start = get_field( 'pickup_start_date', $product_id );
        $product_end   = get_field( 'pickup_end_date', $product_id );

        if ( $product_start ) {
            $start_dt = DateTime::createFromFormat( 'Ymd', $product_start );
            if ( ! $start_date || $start_dt > $start_date ) {
                $start_date = $start_dt; // Use latest start date
            }
        }

        if ( $product_end ) {
            $end_dt = DateTime::createFromFormat( 'Ymd', $product_end );
            if ( ! $end_date || $end_dt < $end_date ) {
                $end_date = $end_dt; // Use earliest end date
            }
        }
    }

    return [
        'start' => $start_date ? $start_date->format( 'Ymd' ) : null,
        'end'   => $end_date   ? $end_date->format( 'Ymd' )   : null,
    ];
}

/**
 * Inject pickup fields HTML into footer.
 * Single selection for entire order.
 */
add_action( 'wp_footer', 'render_pickup_fields_in_footer' );
function render_pickup_fields_in_footer() {
    if ( ! is_checkout() ) {
        return;
    }

    $items     = get_pickup_cart_items();
    $locations = get_shared_pickup_locations();

    if ( empty( $items ) || empty( $locations ) ) {
        return;
    }

    // Build product names list for display
    $product_names = array_unique( array_column( $items, 'name' ) );
    $date_range    = get_combined_pickup_date_range();
    ?>
    <div id="pickup-selection-wrapper" style="display:none;">
        <div id="pickup-selection-inner" style="margin: 20px 0; padding: 20px; border: 1px solid #e5e5e5;">
            <h3 style="margin-bottom: 10px;">Pickup Details</h3>

            <?php if ( count( $product_names ) > 1 ) : ?>
            <p style="margin-bottom: 15px; color: #666;">
                Pickup details apply to:
                <strong><?php echo esc_html( implode( ', ', $product_names ) ); ?></strong>
            </p>
            <?php endif; ?>

            <!-- Single pickup selection for entire order -->
            <div class="pickup-item"
                 data-date-range-start="<?php echo esc_attr( $date_range['start'] ?? '' ); ?>"
                 data-date-range-end="<?php echo esc_attr( $date_range['end'] ?? '' ); ?>">

                <p class="form-row form-row-wide">
                    <label>Pickup Location <span class="required">*</span></label>
                    <select class="pickup-location-select input-text"
                            name="pickup_location_order"
                            style="width:100%; padding: 8px;">
                        <option value="">— Select a location —</option>
                        <?php foreach ( $locations as $location ) : ?>
                        <option value="<?php echo esc_attr( $location['id'] ); ?>"
                                data-address="<?php echo esc_attr( $location['address'] ); ?>">
                            <?php echo esc_html( $location['name'] . ' — ' . $location['address'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p class="form-row form-row-wide pickup-date-row" style="display:none;">
                    <label>Pickup Date <span class="required">*</span></label>
                    <select class="pickup-date-select input-text"
                            name="pickup_date_order"
                            style="width:100%; padding: 8px;">
                        <option value="">— Select a date —</option>
                    </select>
                </p>

                <p class="form-row form-row-wide pickup-time-row" style="display:none;">
                    <label>Pickup Time <span class="required">*</span></label>
                    <select class="pickup-time-select input-text"
                            name="pickup_time_order"
                            style="width:100%; padding: 8px;">
                        <option value="">— Select a time —</option>
                    </select>
                </p>

            </div>
        </div>
    </div>

    <script>
    jQuery(function($) {

        function isLocalPickupSelected() {
            var $radio = $('input[name="shipping_method[0]"]:checked');
            if ( $radio.length ) {
                return $radio.val().indexOf('local_pickup') !== -1;
            }
            var $hidden = $('input[name="shipping_method[0]"][type="hidden"]');
            if ( $hidden.length ) {
                return $hidden.val().indexOf('local_pickup') !== -1;
            }
            return false;
        }

        function injectAndShow() {
    var $inner = $('#pickup-selection-inner');

    if ( ! $inner.parent().is('#payment') && $('#payment').length ) {
        $inner.insertBefore('#payment');
    }

    if ( isLocalPickupSelected() ) {
        $inner.show();

        // Auto-select if only one location option
        $('.pickup-location-select').each(function() {
            var $select  = $(this);
            var $options = $select.find('option[value!=""]');

            if ( $options.length === 1 && $select.val() === '' ) {
                $select.val( $options.first().val() ).trigger('change');
            }
        });

    } else {
        $inner.hide();
    }
}

        setTimeout( injectAndShow, 500 );

        $( document.body ).on( 'updated_checkout', function() {
            setTimeout( injectAndShow, 300 );
        });

        $( document.body ).on( 'change', 'input[name="shipping_method[0]"]', injectAndShow );

    });
    </script>
    <?php
}