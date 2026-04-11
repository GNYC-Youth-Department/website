function tec_venue_address_shortcode() {

    if ( ! function_exists( 'tribe_get_venue_id' ) ) {
        return '';
    }

    $venue_id = tribe_get_venue_id();

    if ( ! $venue_id ) {
        return '';
    }

    $venue_name = tribe_get_venue( $venue_id );
    $address    = tribe_get_address( $venue_id );
    $city       = tribe_get_city( $venue_id );
    $state      = tribe_get_state( $venue_id );
    $zip        = tribe_get_zip( $venue_id );

    $is_pending = in_array(strtolower(trim($venue_name)), ['pending', 'everywhere']);

    $city_state_zip = trim("$city, $state $zip");
    $city_state_zip = preg_replace('/^,\s*/', '', $city_state_zip); // clean if city empty

    $output = '';

    if ( $is_pending ) {

        $output .= '<strong>Location:</strong> ' . esc_html($venue_name);

        if ( $city_state_zip ) {
            $output .= '<br>' . esc_html($city_state_zip);
        }

    } else {

        $output .= '<strong>Location</strong>';

        if ( $venue_name ) {
            $output .= '<br>' . esc_html($venue_name);
        }

        if ( $address ) {
            $output .= '<br>' . esc_html($address);
        }

        if ( $city_state_zip ) {
            $output .= '<br>' . esc_html($city_state_zip);
        }

        $map_query = urlencode("$address $city $state $zip");
        $map_url = "https://www.google.com/maps/search/?api=1&query=$map_query";

        $output .= '<br><a href="' . esc_url($map_url) . '" target="_blank" rel="noopener">View Map</a>';
    }

    return $output;
}

add_shortcode( 'venue_address', 'tec_venue_address_shortcode' );