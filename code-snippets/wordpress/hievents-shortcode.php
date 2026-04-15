function hievents_widget_shortcode($atts) {
    global $post;
    if (!is_singular('tribe_events')) return '';

    $event_id = get_post_meta($post->ID, 'hievents_id', true);
    if (!$event_id) return '';

    $primary_color        = get_post_meta($post->ID, 'hievents_primary_color', true) ?: '#1e4974ff';
    $primary_text_color   = get_post_meta($post->ID, 'hievents_primary_text_color', true) ?: '#000000';
    $secondary_color      = get_post_meta($post->ID, 'hievents_secondary_color', true) ?: '#1e4974ff';
    $secondary_text_color = get_post_meta($post->ID, 'hievents_secondary_text_color', true) ?: '#ffffff';
    $background_color     = get_post_meta($post->ID, 'hievents_background_color', true) ?: '#ffffff';

    return '<div 
        class="hievents-widget"
        data-hievents-id="' . esc_attr($event_id) . '"
        data-hievents-primary-color="' . esc_attr($primary_color) . '"
        data-hievents-primary-text-color="' . esc_attr($primary_text_color) . '"
        data-hievents-secondary-color="' . esc_attr($secondary_color) . '"
        data-hievents-secondary-text-color="' . esc_attr($secondary_text_color) . '"
        data-hievents-background-color="' . esc_attr($background_color) . '"
        data-hievents-widget-type="widget"
        data-hievents-widget-version="1.0"
        data-hievents-locale="en"
        data-hievents-padding="20px"
        data-hievents-autoresize="true"
        data-hievents-continue-button-text="Continue">
    </div>';
}
add_shortcode('hievents', 'hievents_widget_shortcode');