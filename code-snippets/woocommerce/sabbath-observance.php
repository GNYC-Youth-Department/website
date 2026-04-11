/**
 * Sabbath Observance
 * Closes store from Friday sunset to Saturday sunset.
 * Uses Sunrise-Sunset API for accurate NYC sunset times.
 */

/**
 * Get sunset times for Friday and Saturday of the current week.
 * Results are cached for 24 hours.
 */
function get_sabbath_times() {
    $cache_key = 'sabbath_times_' . date( 'W' ); // Cache per week
    $cached    = get_transient( $cache_key );

    if ( $cached ) {
        return $cached;
    }

    $timezone = new DateTimeZone( wp_timezone_string() );
    $now      = new DateTime( 'now', $timezone );

    // Get this week's Friday and Saturday dates
    $friday   = clone $now;
    $saturday = clone $now;

    // Find this week's Friday
    $day_of_week = intval( $now->format( 'N' ) ); // 1=Mon, 5=Fri, 6=Sat, 7=Sun

    if ( $day_of_week <= 5 ) {
        $friday->modify( 'this friday' );
    } elseif ( $day_of_week == 6 ) {
        $friday->modify( 'last friday' );
    } else {
        $friday->modify( 'last friday' );
    }

    $saturday = clone $friday;
    $saturday->modify( '+1 day' );

    // NYC coordinates
    $lat = 40.7128;
    $lng = -74.0060;

    // Fetch Friday sunset
    $friday_date    = $friday->format( 'Y-m-d' );
    $saturday_date  = $saturday->format( 'Y-m-d' );

    $friday_url   = "https://api.sunrise-sunset.org/json?lat={$lat}&lng={$lng}&date={$friday_date}&formatted=0";
    $saturday_url = "https://api.sunrise-sunset.org/json?lat={$lat}&lng={$lng}&date={$saturday_date}&formatted=0";

    $friday_response   = wp_remote_get( $friday_url, [ 'timeout' => 10 ] );
    $saturday_response = wp_remote_get( $saturday_url, [ 'timeout' => 10 ] );

    if ( is_wp_error( $friday_response ) || is_wp_error( $saturday_response ) ) {
        // Fallback to 6 PM if API fails
        $fallback_open  = DateTime::createFromFormat( 'Y-m-d H:i:s', $friday_date . ' 18:00:00', new DateTimeZone( 'UTC' ) );
        $fallback_close = DateTime::createFromFormat( 'Y-m-d H:i:s', $saturday_date . ' 18:00:00', new DateTimeZone( 'UTC' ) );

        return [
            'friday_sunset'   => $fallback_open,
            'saturday_sunset' => $fallback_close,
            'fallback'        => true,
        ];
    }

    $friday_data   = json_decode( wp_remote_retrieve_body( $friday_response ), true );
    $saturday_data = json_decode( wp_remote_retrieve_body( $saturday_response ), true );

    if ( $friday_data['status'] !== 'OK' || $saturday_data['status'] !== 'OK' ) {
        return false;
    }

    // API returns UTC — convert to site timezone
    $friday_sunset   = new DateTime( $friday_data['results']['sunset'], new DateTimeZone( 'UTC' ) );
$saturday_sunset = new DateTime( $saturday_data['results']['sunset'], new DateTimeZone( 'UTC' ) );

$friday_sunset->setTimezone( $timezone );
$saturday_sunset->setTimezone( $timezone );

// Start blocking 15 minutes before Friday sunset
$friday_sunset->modify( '-15 minutes' );

// End blocking 5 minutes after Saturday sunset
$saturday_sunset->modify( '+5 minutes' );

    $result = [
        'friday_sunset'   => $friday_sunset,
        'saturday_sunset' => $saturday_sunset,
        'fallback'        => false,
    ];

    // Cache for 24 hours
    set_transient( $cache_key, $result, DAY_IN_SECONDS );

    return $result;
}

/**
 * Check if it is currently Sabbath.
 */
function is_sabbath() {
    // Check admin override
    if ( get_option( 'sabbath_override_active' ) ) {
        return false;
    }

    $times = get_sabbath_times();
    if ( ! $times ) {
        return false;
    }

    $timezone = new DateTimeZone( wp_timezone_string() );
    $now      = new DateTime( 'now', $timezone );

    return $now >= $times['friday_sunset'] && $now < $times['saturday_sunset'];
}

/**
 * Get Saturday sunset time for display.
 */
function get_sabbath_reopen_time() {
    $times = get_sabbath_times();
    if ( ! $times ) {
        return '';
    }
    return $times['saturday_sunset']->format( 'g:i A' ) . ' on Saturday';
}

/**
 * Show Sabbath banner on shop, product, and cart pages.
 */
add_action( 'wp_footer', 'render_sabbath_banner' );
function render_sabbath_banner() {
    if ( ! is_sabbath() ) {
        return;
    }

    if ( ! is_shop() && ! is_product() && ! is_product_category() && ! is_cart() && ! is_checkout() ) {
        return;
    }

    $reopen_time     = get_sabbath_reopen_time();
    $times           = get_sabbath_times();
    $saturday_sunset = $times['saturday_sunset'];
    ?>
    <style>
    #sabbath-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 999999;
        background: linear-gradient( 135deg, #c8972a, #e8b84b );
        color: #fff;
        padding: 14px 20px;
        text-align: center;
        font-size: 15px;
        font-family: inherit;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    #sabbath-banner strong {
        font-size: 16px;
    }
    #sabbath-banner .sabbath-countdown {
        display: inline-block;
        margin-left: 10px;
        background: rgba(0,0,0,0.15);
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: bold;
    }
    /* Admin bar adjustment */
    body.admin-bar #sabbath-banner {
        top: 32px !important;
    }
</style>
<script>
(function() {
    function adjustForBanner() {
        var banner = document.getElementById('sabbath-banner');
        if ( ! banner ) return;

        var bannerHeight = banner.offsetHeight;
        var isAdminBar   = document.body.classList.contains('admin-bar');
        var adminOffset  = isAdminBar ? 32 : 0;
        var totalOffset  = bannerHeight + adminOffset;

        // Push entire page down (BEST METHOD)
document.body.style.setProperty('padding-top', totalOffset + 'px', 'important');
    }

    // Run on load
    document.addEventListener('DOMContentLoaded', adjustForBanner);

    // Run on resize (handles orientation change on mobile)
    window.addEventListener('resize', adjustForBanner);

    // Run after a short delay to catch lazy-loaded elements
    setTimeout(adjustForBanner, 500);
})();

</script>
    <div id="sabbath-banner">
        🕍 <strong>Our store is closed in observance of Sabbath.</strong>
        We will reopen at <strong><?php echo esc_html( $reopen_time ); ?></strong>.
        <span class="sabbath-countdown" id="sabbath-timer">Loading...</span>
    </div>

    <script>
    (function() {
        var reopenTime = new Date( <?php echo $saturday_sunset->getTimestamp() * 1000; ?> );

        function updateTimer() {
            var now  = new Date();
            var diff = reopenTime - now;

            if ( diff <= 0 ) {
                document.getElementById('sabbath-timer').textContent = 'Reopening now...';
                setTimeout(function() { location.reload(); }, 3000 );
                return;
            }

            var hours   = Math.floor( diff / ( 1000 * 60 * 60 ) );
            var minutes = Math.floor( ( diff % ( 1000 * 60 * 60 ) ) / ( 1000 * 60 ) );
            var seconds = Math.floor( ( diff % ( 1000 * 60 ) ) / 1000 );

            document.getElementById('sabbath-timer').textContent =
                'Reopens in ' + hours + 'h ' +
                String(minutes).padStart(2,'0') + 'm ' +
                String(seconds).padStart(2,'0') + 's';
        }

        updateTimer();
        setInterval( updateTimer, 1000 );
    })();
    </script>
    <?php
}

/**
 * Block add to cart during Sabbath.
 */
add_filter( 'woocommerce_add_to_cart_validation', 'block_cart_during_sabbath', 10, 2 );
function block_cart_during_sabbath( $passed, $product_id ) {
    if ( is_sabbath() ) {
        $reopen = get_sabbath_reopen_time();
        wc_add_notice(
            '🕍 Our store is closed in observance of Sabbath. You can add items to your cart but checkout will be available after ' . $reopen . '.',
            'notice'
        );
    }
    return $passed; // Still allow adding to cart
}

/**
 * Block checkout during Sabbath.
 */
add_action( 'woocommerce_checkout_process', 'block_checkout_during_sabbath' );
function block_checkout_during_sabbath() {
    if ( is_sabbath() ) {
        $reopen = get_sabbath_reopen_time();
        wc_add_notice(
            '🕍 Our store is closed in observance of Sabbath. Checkout will be available after ' . $reopen . '.',
            'error'
        );
    }
}

/**
 * Hide Place Order button during Sabbath and show message instead.
 */
add_action( 'woocommerce_review_order_after_submit', 'sabbath_checkout_message' );
function sabbath_checkout_message() {
    if ( ! is_sabbath() ) {
        return;
    }
    $reopen = get_sabbath_reopen_time();
    ?>
    <p class="sabbath-checkout-notice" style="
        background: #fff8e7;
        border: 1px solid #e8b84b;
        border-radius: 4px;
        padding: 12px 16px;
        margin-top: 10px;
        color: #7a5c00;
        text-align: center;
    ">
        🕍 Checkout is unavailable during Sabbath.<br>
        <strong>Store reopens at <?php echo esc_html( $reopen ); ?></strong>
    </p>
    <?php
}

/**
 * Hide Place Order button during Sabbath via CSS.
 */
add_action( 'wp_head', 'sabbath_hide_place_order' );
function sabbath_hide_place_order() {
    if ( ! is_sabbath() || ! is_checkout() ) {
        return;
    }
    echo '<style>#place_order { display: none !important; }</style>';
}

/**
 * Admin override settings page under WooCommerce.
 */
add_action( 'admin_menu', 'sabbath_override_menu' );
function sabbath_override_menu() {
    add_submenu_page(
        'woocommerce',
        'Sabbath Override',
        'Sabbath Override',
        'manage_options',
        'sabbath-override',
        'sabbath_override_page'
    );
}

function sabbath_override_page() {
    // Handle form submission
    if ( isset( $_POST['sabbath_override_nonce'] ) &&
         wp_verify_nonce( $_POST['sabbath_override_nonce'], 'sabbath_override' ) ) {

        if ( isset( $_POST['sabbath_override'] ) && $_POST['sabbath_override'] === '1' ) {
            update_option( 'sabbath_override_active', true );
            $message = 'Sabbath override enabled — store is now open.';
        } else {
            delete_option( 'sabbath_override_active' );
            $message = 'Sabbath override disabled — normal Sabbath observance resumed.';
        }
    }

    $is_sabbath          = is_sabbath();
    $override_active     = get_option( 'sabbath_override_active' );
    $times               = get_sabbath_times();
    $friday_sunset_str   = $times ? $times['friday_sunset']->format( 'l, F j \a\t g:i A' ) : 'unavailable';
    $saturday_sunset_str = $times ? $times['saturday_sunset']->format( 'l, F j \a\t g:i A' ) : 'unavailable';
    ?>
    <div class="wrap">
        <h1>Sabbath Override</h1>

        <?php if ( isset( $message ) ) : ?>
        <div class="notice notice-success"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; max-width:600px; border-radius:4px;">

            <h2 style="margin-top:0;">Current Status</h2>
            <table class="widefat" style="margin-bottom:20px;">
                <tr>
                    <td><strong>Current time</strong></td>
                    <td><?php echo ( new DateTime( 'now', new DateTimeZone( wp_timezone_string() ) ) )->format( 'l, F j \a\t g:i A' ); ?></td>
                </tr>
                <tr>
                    <td><strong>Friday sunset (Sabbath begins)</strong></td>
                    <td><?php echo esc_html( $friday_sunset_str ); ?></td>
                </tr>
                <tr>
                    <td><strong>Saturday sunset (Sabbath ends)</strong></td>
                    <td><?php echo esc_html( $saturday_sunset_str ); ?></td>
                </tr>
                <tr>
                    <td><strong>Sabbath active?</strong></td>
                    <td>
                        <?php if ( $override_active ) : ?>
                            <span style="color:#d63638;">Yes (but overridden — store is open)</span>
                        <?php elseif ( $is_sabbath ) : ?>
                            <span style="color:#d63638;">Yes — store is closed</span>
                        <?php else : ?>
                            <span style="color:#00a32a;">No — store is open</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Override active?</strong></td>
                    <td>
                        <?php echo $override_active
                            ? '<span style="color:#d63638;">Yes — Sabbath restrictions bypassed</span>'
                            : '<span style="color:#00a32a;">No</span>'; ?>
                    </td>
                </tr>
            </table>

            <form method="post">
                <?php wp_nonce_field( 'sabbath_override', 'sabbath_override_nonce' ); ?>

                <?php if ( $override_active ) : ?>
                    <p>The Sabbath override is currently <strong>active</strong>. The store is open even during Sabbath hours.</p>
                    <input type="hidden" name="sabbath_override" value="0">
                    <button type="submit" class="button button-secondary">
                        Disable Override — Resume Sabbath Observance
                    </button>
                <?php else : ?>
                    <p>Enable the override to keep the store open during Sabbath hours. Use only when necessary.</p>
                    <input type="hidden" name="sabbath_override" value="1">
                    <button type="submit" class="button button-primary">
                        Enable Override — Open Store During Sabbath
                    </button>
                <?php endif; ?>

            </form>

            <p style="margin-top:20px; color:#666; font-size:12px;">
                Sunset times are fetched from the Sunrise-Sunset API using New York City coordinates
                and cached weekly. The override resets automatically when you disable it.
            </p>

        </div>
    </div>
    <?php
}

/**
 * Clear sabbath time cache when override is toggled.
 */
add_action( 'update_option_sabbath_override_active', function() {
    delete_transient( 'sabbath_times_' . date( 'W' ) );
});