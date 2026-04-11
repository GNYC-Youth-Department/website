/**
 * Checkout Custom Fields
 * - Church affiliation (from EspoCRM) — all orders
 * - Alternate pickup person — local pickup orders only
 */

/**
 * Fetch churches from EspoCRM API.
 * Cached for 24 hours.
 */
function get_espocrm_churches() {
    $cache_key = 'espocrm_churches';
    $cached    = get_transient( $cache_key );

    if ( $cached !== false ) {
        return $cached;
    }

    $api_key = defined( 'ESPOCRM_API_KEY' ) ? ESPOCRM_API_KEY : '';

    if ( empty( $api_key ) ) {
        return [];
    }

    $url = 'https://crm.gnycyouth.org/api/v1/Account?' . http_build_query([
    'searchParams' => json_encode([
        'where' => [
            [
                'type'  => 'or',
                'value' => [
                    [
                        'type'      => 'equals',
                        'attribute' => 'type',
                        'value'     => 'Church',
                    ],
                    [
                        'type'      => 'equals',
                        'attribute' => 'type',
                        'value'     => 'Company',
                    ],
                    [
                        'type'      => 'equals',
                        'attribute' => 'type',
                        'value'     => 'Group',
                    ],
                ],
            ],
        ],
        'select'  => [ 'id', 'name', 'type' ],
        'orderBy' => 'name',
        'order'   => 'asc',
    ]),
]);
    $response = wp_remote_get( $url, [
        'timeout' => 15,
        'headers' => [
            'X-Api-Key' => $api_key,
        ],
    ]);

    if ( is_wp_error( $response ) ) {
        error_log( 'EspoCRM API error: ' . $response->get_error_message() );
        return [];
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code !== 200 ) {
        error_log( 'EspoCRM API returned status: ' . $code );
        return [];
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['list'] ) ) {
        return [];
    }

    $churches = [];
    foreach ( $body['list'] as $church ) {
        $churches[] = [
            'id'   => $church['id'],
            'name' => $church['name'],
        ];
    }

    // Cache for 24 hours
    set_transient( $cache_key, $churches, DAY_IN_SECONDS );

    return $churches;
}

/**
 * Check if local pickup is the selected shipping method.
 */
function checkout_is_local_pickup() {
    if ( ! WC()->session ) {
        return false;
    }
    $chosen = WC()->session->get( 'chosen_shipping_methods' );
    if ( empty( $chosen ) ) {
        return false;
    }
    foreach ( $chosen as $method ) {
        if ( strpos( $method, 'local_pickup' ) !== false ) {
            return true;
        }
    }
    return false;
}

/**
 * Inject custom fields above billing details.
 */
add_action( 'woocommerce_before_checkout_billing_form', 'render_custom_checkout_fields' );
function render_custom_checkout_fields() {
    $churches        = get_espocrm_churches();
    $is_local_pickup = checkout_is_local_pickup();
    ?>
    <div id="custom-checkout-fields" style="margin-bottom: 30px;">

        <?php if ( ! empty( $churches ) ) : ?>
        <!-- Church Affiliation -->
        <div class="woocommerce-billing-fields">
            <h3>Church Information</h3>
            <p class="form-row form-row-wide" id="church_affiliation_field">
                <label for="church_affiliation">
                    Church Affiliation <span class="required">*</span>
                </label>
                <select
                    name="church_affiliation_id"
                    id="church_affiliation"
                    class="select input-text"
                    style="width: 100%; padding: 8px;"
                >
                    <option value="">— Select your church —</option>
                    <?php foreach ( $churches as $church ) : ?>
                    <option value="<?php echo esc_attr( $church['id'] ); ?>"
                            data-name="<?php echo esc_attr( $church['name'] ); ?>">
                        <?php echo esc_html( $church['name'] ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </p>
        </div>
        <?php endif; ?>

        <!-- Alternate Pickup Person — only shown for local pickup -->
        <div
            id="alternate-pickup-wrapper"
            class="woocommerce-billing-fields"
            style="<?php echo $is_local_pickup ? '' : 'display:none;'; ?>"
        >
            <h3>Alternate Pickup Person</h3>
            <p class="form-row form-row-wide">
                <label for="has_alternate_pickup">
                    Would you like to designate an alternate pickup person?
                </label>
                <select
                    name="has_alternate_pickup"
                    id="has_alternate_pickup"
                    class="select input-text"
                    style="width: 100%; padding: 8px;"
                >
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                </select>
            </p>

            <div id="alternate-pickup-fields" style="display:none;">
                <p class="form-row form-row-wide">
                    <label for="alternate_pickup_name">
                        Full Name <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="alternate_pickup_name"
                        id="alternate_pickup_name"
                        class="input-text"
                        style="width: 100%; padding: 8px;"
                        placeholder="Full name"
                    />
                </p>
                <p class="form-row form-row-first">
                    <label for="alternate_pickup_phone">
                        Phone Number <span class="required">*</span>
                    </label>
                    <input
                        type="tel"
                        name="alternate_pickup_phone"
                        id="alternate_pickup_phone"
                        class="input-text"
                        style="width: 100%; padding: 8px;"
                        placeholder="Phone number"
                    />
                </p>
                <p class="form-row form-row-last">
                    <label for="alternate_pickup_email">
                        Email Address <span class="required">*</span>
                    </label>
                    <input
                        type="email"
                        name="alternate_pickup_email"
                        id="alternate_pickup_email"
                        class="input-text"
                        style="width: 100%; padding: 8px;"
                        placeholder="Email address"
                    />
                </p>
            </div>
        </div>

    </div>

    <script>
    jQuery(function($) {

        // Show/hide alternate pickup fields based on Yes/No
        $('#has_alternate_pickup').on('change', function() {
            if ( $(this).val() === 'yes' ) {
                $('#alternate-pickup-fields').slideDown(200);
            } else {
                $('#alternate-pickup-fields').slideUp(200);
            }
        });

        // Show/hide entire alternate pickup section based on shipping method
        function toggleAlternatePickup() {
            var $chosen = $('input[name="shipping_method[0]"]:checked');
            var method  = $chosen.length ? $chosen.val() : $('input[name="shipping_method[0]"]').val();

            if ( method && method.indexOf('local_pickup') !== -1 ) {
                $('#alternate-pickup-wrapper').slideDown(200);
            } else {
                $('#alternate-pickup-wrapper').slideUp(200);
                $('#has_alternate_pickup').val('no').trigger('change');
            }
        }

        toggleAlternatePickup();

        $( document.body ).on( 'updated_checkout', toggleAlternatePickup );
        $( document.body ).on( 'change', 'input[name="shipping_method[0]"]', toggleAlternatePickup );

    });
    </script>
    <?php
}

/**
 * Validate custom checkout fields.
 */
add_action( 'woocommerce_checkout_process', 'validate_custom_checkout_fields' );
function validate_custom_checkout_fields() {

    // Church affiliation — required for all orders
    if ( empty( $_POST['church_affiliation_id'] ) ) {
        wc_add_notice( 'Please select your church affiliation.', 'error' );
    }

    // Alternate pickup person — only validate if local pickup and Yes selected
    $chosen = WC()->session->get( 'chosen_shipping_methods' );
    $is_local_pickup = false;
    foreach ( ( $chosen ?: [] ) as $method ) {
        if ( strpos( $method, 'local_pickup' ) !== false ) {
            $is_local_pickup = true;
            break;
        }
    }

    if ( $is_local_pickup && isset( $_POST['has_alternate_pickup'] ) && $_POST['has_alternate_pickup'] === 'yes' ) {
        if ( empty( $_POST['alternate_pickup_name'] ) ) {
            wc_add_notice( 'Please enter the alternate pickup person\'s full name.', 'error' );
        }
        if ( empty( $_POST['alternate_pickup_phone'] ) ) {
            wc_add_notice( 'Please enter the alternate pickup person\'s phone number.', 'error' );
        }
        if ( empty( $_POST['alternate_pickup_email'] ) ) {
            wc_add_notice( 'Please enter the alternate pickup person\'s email address.', 'error' );
        } elseif ( ! is_email( $_POST['alternate_pickup_email'] ) ) {
            wc_add_notice( 'Please enter a valid email address for the alternate pickup person.', 'error' );
        }
    }
}

/**
 * Save custom fields to order meta.
 */
add_action( 'woocommerce_checkout_order_processed', 'save_custom_checkout_fields', 10, 3 );
function save_custom_checkout_fields( $order_id, $posted_data, $order ) {

    // Church affiliation
    if ( ! empty( $_POST['church_affiliation_id'] ) ) {
        $church_id   = sanitize_text_field( $_POST['church_affiliation_id'] );
        $church_name = '';

        // Get name from cached churches list
        $churches = get_espocrm_churches();
        foreach ( $churches as $church ) {
            if ( $church['id'] === $church_id ) {
                $church_name = $church['name'];
                break;
            }
        }

        $order->update_meta_data( '_church_affiliation_id', $church_id );
        $order->update_meta_data( '_church_affiliation_name', $church_name );
    }

    // Alternate pickup person
    $has_alternate = isset( $_POST['has_alternate_pickup'] )
        ? sanitize_text_field( $_POST['has_alternate_pickup'] )
        : 'no';

    $order->update_meta_data( '_has_alternate_pickup', $has_alternate );

    if ( $has_alternate === 'yes' ) {
        $order->update_meta_data( '_alternate_pickup_name',  sanitize_text_field( $_POST['alternate_pickup_name'] ?? '' ) );
        $order->update_meta_data( '_alternate_pickup_phone', sanitize_text_field( $_POST['alternate_pickup_phone'] ?? '' ) );
        $order->update_meta_data( '_alternate_pickup_email', sanitize_email( $_POST['alternate_pickup_email'] ?? '' ) );
    }

    $order->save();
}

/**
 * Display custom fields in order admin.
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_custom_fields_in_order_admin', 10, 1 );
function display_custom_fields_in_order_admin( $order ) {
    $church_name   = $order->get_meta( '_church_affiliation_name' );
    $church_id     = $order->get_meta( '_church_affiliation_id' );
    $has_alternate = $order->get_meta( '_has_alternate_pickup' );

    if ( empty( $church_name ) && empty( $has_alternate ) ) {
        return;
    }

    echo '<div style="margin-top: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #e5e5e5;">';
    echo '<h4 style="margin-bottom: 10px;">Additional Order Information</h4>';

    if ( ! empty( $church_name ) ) {
        echo '<p><strong>Church:</strong> ' . esc_html( $church_name ) . '</p>';
        echo '<p style="color:#999; font-size:11px;">EspoCRM ID: ' . esc_html( $church_id ) . '</p>';
    }

    if ( $has_alternate === 'yes' ) {
        echo '<hr style="margin: 10px 0;">';
        echo '<p><strong>Alternate Pickup Person</strong></p>';
        echo '<p><strong>Name:</strong> '  . esc_html( $order->get_meta( '_alternate_pickup_name' ) )  . '</p>';
        echo '<p><strong>Phone:</strong> ' . esc_html( $order->get_meta( '_alternate_pickup_phone' ) ) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html( $order->get_meta( '_alternate_pickup_email' ) ) . '</p>';
    }

    echo '</div>';
}

/**
 * Display custom fields in order confirmation email.
 */
add_action( 'woocommerce_email_order_details', 'display_custom_fields_in_email', 4, 4 );
function display_custom_fields_in_email( $order, $sent_to_admin, $plain_text, $email ) {
    $church_name   = $order->get_meta( '_church_affiliation_name' );
    $has_alternate = $order->get_meta( '_has_alternate_pickup' );

    if ( empty( $church_name ) && $has_alternate !== 'yes' ) {
        return;
    }

    if ( $plain_text ) {
        echo "\n\nADDITIONAL ORDER INFORMATION\n";
        echo "============================\n";
        if ( ! empty( $church_name ) ) {
            echo 'Church: ' . $church_name . "\n";
        }
        if ( $has_alternate === 'yes' ) {
            echo 'Alternate Pickup Person: ' . $order->get_meta( '_alternate_pickup_name' )  . "\n";
            echo 'Phone: '                   . $order->get_meta( '_alternate_pickup_phone' ) . "\n";
            echo 'Email: '                   . $order->get_meta( '_alternate_pickup_email' ) . "\n";
        }
    } else {
        echo '<div style="margin-bottom: 40px;">';
        echo '<h2 style="color: #96588a;">Additional Order Information</h2>';
        echo '<table cellspacing="0" cellpadding="6" style="width:100%; border:1px solid #e5e5e5;">';

        if ( ! empty( $church_name ) ) {
            echo '<tr>';
            echo '<th style="text-align:left; border:1px solid #e5e5e5; padding:8px;">Church</th>';
            echo '<td style="border:1px solid #e5e5e5; padding:8px;">' . esc_html( $church_name ) . '</td>';
            echo '</tr>';
        }

        if ( $has_alternate === 'yes' ) {
            echo '<tr>';
            echo '<th style="text-align:left; border:1px solid #e5e5e5; padding:8px;">Alternate Pickup</th>';
            echo '<td style="border:1px solid #e5e5e5; padding:8px;">';
            echo esc_html( $order->get_meta( '_alternate_pickup_name' ) )  . '<br>';
            echo esc_html( $order->get_meta( '_alternate_pickup_phone' ) ) . '<br>';
            echo esc_html( $order->get_meta( '_alternate_pickup_email' ) );
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';
    }
}

/**
 * Display custom fields on customer order page.
 */
add_action( 'woocommerce_order_details_after_order_table', 'display_custom_fields_on_order_page', 5, 1 );
function display_custom_fields_on_order_page( $order ) {
    $church_name   = $order->get_meta( '_church_affiliation_name' );
    $has_alternate = $order->get_meta( '_has_alternate_pickup' );

    if ( empty( $church_name ) && $has_alternate !== 'yes' ) {
        return;
    }

    echo '<section class="woocommerce-order-additional-info">';
    echo '<h2 style="margin-top: 30px;">Additional Order Information</h2>';
    echo '<table class="woocommerce-table" cellspacing="0">';

    if ( ! empty( $church_name ) ) {
        echo '<tr><th>Church</th><td>' . esc_html( $church_name ) . '</td></tr>';
    }

    if ( $has_alternate === 'yes' ) {
        echo '<tr><th>Alternate Pickup Person</th><td>';
        echo esc_html( $order->get_meta( '_alternate_pickup_name' ) )  . '<br>';
        echo esc_html( $order->get_meta( '_alternate_pickup_phone' ) ) . '<br>';
        echo esc_html( $order->get_meta( '_alternate_pickup_email' ) );
        echo '</td></tr>';
    }

    echo '</table>';
    echo '</section>';
}

/**
 * Hide shipping address fields for local pickup orders.
 */
add_action( 'wp_footer', 'hide_shipping_for_local_pickup' );
function hide_shipping_for_local_pickup() {
    if ( ! is_checkout() ) {
        return;
    }
    ?>
    <script>
    jQuery(function($) {

        var lastMethod = null;

        function toggleShippingAddress() {
            var $chosen = $('input[name="shipping_method[0]"]:checked');
            var method  = $chosen.length
                ? $chosen.val()
                : $('input[name="shipping_method[0]"]').val();

            // Only act if method has changed to prevent loop
            if ( method === lastMethod ) {
                return;
            }
            lastMethod = method;

            if ( method && method.indexOf('local_pickup') !== -1 ) {
                $( '#ship-to-different-address' ).hide();
                $( '.woocommerce-shipping-fields' ).hide();
                // Uncheck without triggering updated_checkout
                var $checkbox = $( '#ship-to-different-address-checkbox' );
                if ( $checkbox.is(':checked') ) {
                    $checkbox.prop( 'checked', false );
                }
            } else {
                $( '#ship-to-different-address' ).show();
                $( '.woocommerce-shipping-fields' ).show();
            }
        }

        setTimeout( toggleShippingAddress, 600 );

        $( document.body ).on( 'updated_checkout', function() {
            setTimeout( toggleShippingAddress, 400 );
        });

        $( document.body ).on( 'change', 'input[name="shipping_method[0]"]', function() {
            lastMethod = null; // Reset so change is detected
            toggleShippingAddress();
        });

    });
    </script>
    <?php
}

/**
 * Clear EspoCRM church cache — can be triggered manually.
 * Visit: https://gnycyouth.org/?clear_church_cache=1 (admin only)
 */
add_action( 'init', function() {
    if ( isset( $_GET['clear_church_cache'] ) && current_user_can( 'manage_options' ) ) {
        delete_transient( 'espocrm_churches' );
        wp_die( 'Church cache cleared. It will be refreshed on the next checkout visit.' );
    }
});
