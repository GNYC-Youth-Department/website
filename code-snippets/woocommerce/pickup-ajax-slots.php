/**
 * AJAX handler to get available pickup dates for a location.
 * Incorporates default weekly hours, specific schedule overrides,
 * closed dates, and lead time with buffer slots.
 */
add_action( 'wp_ajax_get_pickup_dates', 'get_pickup_dates_handler' );
add_action( 'wp_ajax_nopriv_get_pickup_dates', 'get_pickup_dates_handler' );

function get_pickup_dates_handler() {
    check_ajax_referer( 'pickup_nonce', 'nonce' );

    $location_id = intval( $_POST['location_id'] );
    $date_start  = sanitize_text_field( $_POST['date_start'] ?? '' );
    $date_end    = sanitize_text_field( $_POST['date_end'] ?? '' );

    if ( ! $location_id ) {
        wp_send_json_error( 'Missing location' );
    }

    $schedule      = get_field( 'pickup_schedule', $location_id );
    $default_hours = get_field( 'default_weekly_hours', $location_id );
    $closed_dates  = get_field( 'closed_dates', $location_id );
    $lead_time     = intval( get_field( 'lead_time_hours', $location_id ) ?: 24 );
    $timezone      = new DateTimeZone( wp_timezone_string() );
    $now           = new DateTime( 'now', $timezone );
    $today         = new DateTime( 'today', $timezone );

    // Build closed dates lookup
    $closed_lookup = [];
    if ( ! empty( $closed_dates ) ) {
        foreach ( $closed_dates as $closed ) {
            if ( ! empty( $closed['closed_date'] ) ) {
                $closed_lookup[ $closed['closed_date'] ] = $closed['closed_reason'] ?? '';
            }
        }
    }

    // Build specific schedule lookup
    $specific_schedule = [];
    if ( ! empty( $schedule ) ) {
        foreach ( $schedule as $row ) {
            if ( empty( $row['schedule_dates'] ) || empty( $row['schedule_time_ranges'] ) ) {
                continue;
            }
            foreach ( $row['schedule_dates'] as $date_row ) {
                if ( ! empty( $date_row['schedule_date'] ) ) {
                    $specific_schedule[ $date_row['schedule_date'] ] = $row['schedule_time_ranges'];
                }
            }
        }
    }

    // Build default hours lookup
    $default_lookup = [];
    if ( ! empty( $default_hours ) ) {
        foreach ( $default_hours as $row ) {
            if ( ! empty( $row['day_of_week'] ) && ! empty( $row['default_time_ranges'] ) ) {
                $default_lookup[ strtolower( $row['day_of_week'] ) ] = $row['default_time_ranges'];
            }
        }
    }

    /**
     * Get time ranges for a specific date.
     */
    $get_time_ranges = function( $date_ymd, $day_name ) use ( $specific_schedule, $default_lookup ) {
        if ( isset( $specific_schedule[ $date_ymd ] ) ) {
            return $specific_schedule[ $date_ymd ];
        }
        if ( isset( $default_lookup[ $day_name ] ) ) {
            return $default_lookup[ $day_name ];
        }
        return [];
    };

    /**
     * Get closing time for a specific date.
     * Returns H:i string of the last end time across all ranges.
     */
    $get_closing_time = function( $date_ymd, $day_name ) use ( $get_time_ranges ) {
        $ranges      = $get_time_ranges( $date_ymd, $day_name );
        $closing     = null;

        foreach ( $ranges as $range ) {
            if ( empty( $range['end_time'] ) ) {
                continue;
            }
            if ( $closing === null || $range['end_time'] > $closing ) {
                $closing = $range['end_time'];
            }
        }

        return $closing;
    };

    /**
     * Calculate the earliest pickup datetime based on lead time.
     * If order is placed after today's closing time,
     * lead time starts from next available day's opening.
     */
    $today_ymd      = $today->format( 'Ymd' );
    $today_day_name = strtolower( $today->format( 'l' ) );
    $today_closing  = $get_closing_time( $today_ymd, $today_day_name );
    $now_time_str   = $now->format( 'H:i' );

    // Determine lead time start point
    if ( $today_closing && $now_time_str < $today_closing ) {
        // Before closing — lead time starts now
        $lead_start = clone $now;
    } else {
        // After closing or no hours today — find next available day's opening
        $search      = clone $today;
        $lead_start  = null;

        for ( $i = 1; $i <= 14; $i++ ) {
            $search->modify( '+1 day' );
            $search_ymd      = $search->format( 'Ymd' );
            $search_day_name = strtolower( $search->format( 'l' ) );

            // Skip closed dates
            if ( isset( $closed_lookup[ $search_ymd ] ) ) {
                continue;
            }

            $ranges = $get_time_ranges( $search_ymd, $search_day_name );
            if ( empty( $ranges ) ) {
                continue;
            }

            // Get opening time (earliest start across all ranges)
            $opening = null;
            foreach ( $ranges as $range ) {
                if ( empty( $range['start_time'] ) ) {
                    continue;
                }
                if ( $opening === null || $range['start_time'] < $opening ) {
                    $opening = $range['start_time'];
                }
            }

            if ( $opening ) {
                $lead_start = DateTime::createFromFormat(
                    'Y-m-d H:i',
                    $search->format( 'Y-m-d' ) . ' ' . $opening,
                    $timezone
                );
                break;
            }
        }

        // Fallback if no next day found
        if ( ! $lead_start ) {
            $lead_start = clone $now;
        }
    }

    // Add lead time hours to get earliest pickup datetime
    $earliest_pickup = clone $lead_start;
    $earliest_pickup->modify( '+' . $lead_time . ' hours' );

    // Determine date window
    $start_dt = null;
    $end_dt   = null;

    if ( ! empty( $date_start ) ) {
        $start_dt = DateTime::createFromFormat( 'Ymd', $date_start );
    }
    if ( ! empty( $date_end ) ) {
        $end_dt = DateTime::createFromFormat( 'Ymd', $date_end );
    }

    if ( ! $start_dt || $start_dt < $today ) {
        $start_dt = clone $today;
    }
    if ( ! $end_dt ) {
        $end_dt = clone $today;
        $end_dt->modify( '+90 days' );
    }

    $available_dates = [];
    $current         = clone $start_dt;

    while ( $current <= $end_dt ) {
        $date_ymd  = $current->format( 'Ymd' );
        $day_name  = strtolower( $current->format( 'l' ) );

        // Skip closed dates
        if ( isset( $closed_lookup[ $date_ymd ] ) ) {
            $current->modify( '+1 day' );
            continue;
        }

        $time_ranges = $get_time_ranges( $date_ymd, $day_name );

        if ( empty( $time_ranges ) ) {
            $current->modify( '+1 day' );
            continue;
        }

        // Check if this date has any slots available after lead time + buffer
        $has_available_slots = false;
        $slot_index          = 0;

        foreach ( $time_ranges as $range ) {
            if ( empty( $range['start_time'] ) || empty( $range['end_time'] ) ) {
                continue;
            }

            $slot_current = DateTime::createFromFormat(
                'Y-m-d H:i',
                $current->format( 'Y-m-d' ) . ' ' . $range['start_time'],
                $timezone
            );
            $slot_end = DateTime::createFromFormat(
                'Y-m-d H:i',
                $current->format( 'Y-m-d' ) . ' ' . $range['end_time'],
                $timezone
            );

            if ( ! $slot_current || ! $slot_end ) {
                continue;
            }

            while ( $slot_current < $slot_end ) {
                $slot_index++;

                // Skip first 4 slots as buffer on the earliest eligible day
                $is_earliest_day = $date_ymd === $earliest_pickup->format( 'Ymd' );
                if ( $is_earliest_day && $slot_index <= 4 ) {
                    $slot_current->modify( '+15 minutes' );
                    continue;
                }

                // Check if slot is after earliest pickup time
                if ( $slot_current >= $earliest_pickup ) {
                    $has_available_slots = true;
                    break 2;
                }

                $slot_current->modify( '+15 minutes' );
            }
        }

        if ( $has_available_slots ) {
            $available_dates[] = [
                'value' => $date_ymd,
                'label' => $current->format( 'l, F j, Y' ),
            ];
        }

        $current->modify( '+1 day' );
    }

    if ( empty( $available_dates ) ) {
        wp_send_json_error( 'No available dates for this location' );
    }

    wp_send_json_success( [ 'dates' => $available_dates ] );
}

/**
 * AJAX handler to get available time slots for a location/date combo.
 */
add_action( 'wp_ajax_get_pickup_slots', 'get_pickup_slots_handler' );
add_action( 'wp_ajax_nopriv_get_pickup_slots', 'get_pickup_slots_handler' );

function get_pickup_slots_handler() {
    check_ajax_referer( 'pickup_nonce', 'nonce' );

    $location_id = intval( $_POST['location_id'] );
    $date        = sanitize_text_field( $_POST['date'] );

    if ( ! $location_id || ! $date ) {
        wp_send_json_error( 'Missing parameters' );
    }

    $capacity      = get_field( 'location_capacity', $location_id );
    $capacity      = $capacity ? intval( $capacity ) : 5;
    $schedule      = get_field( 'pickup_schedule', $location_id );
    $default_hours = get_field( 'default_weekly_hours', $location_id );
    $closed_dates  = get_field( 'closed_dates', $location_id );
    $lead_time     = intval( get_field( 'lead_time_hours', $location_id ) ?: 24 );
    $timezone      = new DateTimeZone( wp_timezone_string() );
    $now           = new DateTime( 'now', $timezone );
    $today         = new DateTime( 'today', $timezone );

    // Check if date is closed
    if ( ! empty( $closed_dates ) ) {
        foreach ( $closed_dates as $closed ) {
            if ( ! empty( $closed['closed_date'] ) && $closed['closed_date'] === $date ) {
                wp_send_json_error( 'Location is closed on this date' );
            }
        }
    }

    // Build specific schedule lookup
    $specific_schedule = [];
    if ( ! empty( $schedule ) ) {
        foreach ( $schedule as $row ) {
            if ( empty( $row['schedule_dates'] ) || empty( $row['schedule_time_ranges'] ) ) {
                continue;
            }
            foreach ( $row['schedule_dates'] as $date_row ) {
                if ( ! empty( $date_row['schedule_date'] ) ) {
                    $specific_schedule[ $date_row['schedule_date'] ] = $row['schedule_time_ranges'];
                }
            }
        }
    }

    // Build default hours lookup
    $default_lookup = [];
    if ( ! empty( $default_hours ) ) {
        foreach ( $default_hours as $row ) {
            if ( ! empty( $row['day_of_week'] ) && ! empty( $row['default_time_ranges'] ) ) {
                $default_lookup[ strtolower( $row['day_of_week'] ) ] = $row['default_time_ranges'];
            }
        }
    }

    // Find time ranges for selected date
    $time_ranges = [];
    if ( isset( $specific_schedule[ $date ] ) ) {
        $time_ranges = $specific_schedule[ $date ];
    } else {
        $date_dt = DateTime::createFromFormat( 'Ymd', $date );
        if ( $date_dt ) {
            $day_name = strtolower( $date_dt->format( 'l' ) );
            if ( isset( $default_lookup[ $day_name ] ) ) {
                $time_ranges = $default_lookup[ $day_name ];
            }
        }
    }

    if ( empty( $time_ranges ) ) {
        wp_send_json_error( 'No time ranges for this date' );
    }

    // Build closed dates lookup for lead time calculation
    $closed_lookup = [];
    if ( ! empty( $closed_dates ) ) {
        foreach ( $closed_dates as $closed ) {
            if ( ! empty( $closed['closed_date'] ) ) {
                $closed_lookup[ $closed['closed_date'] ] = true;
            }
        }
    }

    /**
     * Get closing time for today.
     */
    $today_ymd      = $today->format( 'Ymd' );
    $today_day_name = strtolower( $today->format( 'l' ) );
    $today_ranges   = isset( $specific_schedule[ $today_ymd ] )
        ? $specific_schedule[ $today_ymd ]
        : ( isset( $default_lookup[ $today_day_name ] ) ? $default_lookup[ $today_day_name ] : [] );

    $today_closing = null;
    foreach ( $today_ranges as $range ) {
        if ( ! empty( $range['end_time'] ) && ( $today_closing === null || $range['end_time'] > $today_closing ) ) {
            $today_closing = $range['end_time'];
        }
    }

    $now_time_str = $now->format( 'H:i' );

    // Calculate earliest pickup datetime
    if ( $today_closing && $now_time_str < $today_closing ) {
        $lead_start = clone $now;
    } else {
        // Find next available day's opening
        $search     = clone $today;
        $lead_start = null;

        for ( $i = 1; $i <= 14; $i++ ) {
            $search->modify( '+1 day' );
            $search_ymd      = $search->format( 'Ymd' );
            $search_day_name = strtolower( $search->format( 'l' ) );

            if ( isset( $closed_lookup[ $search_ymd ] ) ) {
                continue;
            }

            $search_ranges = isset( $specific_schedule[ $search_ymd ] )
                ? $specific_schedule[ $search_ymd ]
                : ( isset( $default_lookup[ $search_day_name ] ) ? $default_lookup[ $search_day_name ] : [] );

            $opening = null;
            foreach ( $search_ranges as $range ) {
                if ( ! empty( $range['start_time'] ) && ( $opening === null || $range['start_time'] < $opening ) ) {
                    $opening = $range['start_time'];
                }
            }

            if ( $opening ) {
                $lead_start = DateTime::createFromFormat(
                    'Y-m-d H:i',
                    $search->format( 'Y-m-d' ) . ' ' . $opening,
                    $timezone
                );
                break;
            }
        }

        if ( ! $lead_start ) {
            $lead_start = clone $now;
        }
    }

    $earliest_pickup  = clone $lead_start;
    $earliest_pickup->modify( '+' . $lead_time . ' hours' );
    $earliest_day_ymd = $earliest_pickup->format( 'Ymd' );
    $is_earliest_day  = $date === $earliest_day_ymd;

    // Generate slots
    global $wpdb;
    $table      = $wpdb->prefix . 'pickup_bookings';
    $slots      = [];
    $slot_index = 0;

    foreach ( $time_ranges as $range ) {
        if ( empty( $range['start_time'] ) || empty( $range['end_time'] ) ) {
            continue;
        }

        $current = DateTime::createFromFormat(
            'Y-m-d H:i',
            ( new DateTime( $date, $timezone ) )->format( 'Y-m-d' ) . ' ' . $range['start_time'],
            $timezone
        );
        $end = DateTime::createFromFormat(
            'Y-m-d H:i',
            ( new DateTime( $date, $timezone ) )->format( 'Y-m-d' ) . ' ' . $range['end_time'],
            $timezone
        );

        if ( ! $current || ! $end ) {
            continue;
        }

        while ( $current < $end ) {
            $slot_time  = $current->format( 'H:i' );
            $slot_label = $current->format( 'g:i A' );
            $slot_index++;

            // Skip first 4 slots as morning buffer on earliest eligible day
            if ( $is_earliest_day && $slot_index <= 4 ) {
                $current->modify( '+15 minutes' );
                continue;
            }

            // Skip slots before earliest pickup time
            if ( $current < $earliest_pickup ) {
                $current->modify( '+15 minutes' );
                continue;
            }

            // Check bookings
            $booked = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table
                WHERE location_id = %d
                AND pickup_date = %s
                AND pickup_time = %s",
                $location_id,
                $date,
                $slot_time
            ));

            $available = intval( $capacity ) - intval( $booked );

            if ( $available > 0 ) {
                $slots[] = [
                    'value' => $slot_time,
                    'label' => $slot_label . ' (' . $available . ' spots left)',
                ];
            }

            $current->modify( '+15 minutes' );
        }
    }

    if ( empty( $slots ) ) {
        wp_send_json_error( 'No available slots for this date' );
    }

    wp_send_json_success( [ 'slots' => $slots ] );
}