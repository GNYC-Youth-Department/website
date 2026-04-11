function hievents_widget_shortcode($atts) {
    global $post;

    // Only show on single events
    if (!is_singular('tribe_events')) return '';

    // Get Hi.Events ID from custom field
    $event_id = get_post_meta($post->ID, 'hievents_id', true);
    if (!$event_id) return '';
	
	// Enqueue local widget.js (replace with your local path)
    wp_enqueue_script(
        'hievents-widget-js', // script handle
        get_stylesheet_directory_uri() . '/wp-content/widget.js', // path to your local copy
        array(), // dependencies
        null, // version
        true  // load in footer
    );
	
    // Output the widget container
    return '<div 
        class="hievents-widget" 
        data-hievents-id="' . esc_attr($event_id) . '" 
        data-hievents-autoresize="true">
    </div>';
}
add_shortcode('hievents', 'hievents_widget_shortcode');