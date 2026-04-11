/**
 * Remove unwanted items from the WordPress admin bar.
 */
add_action( 'admin_bar_menu', 'customize_admin_bar', 999 );
function customize_admin_bar( $wp_admin_bar ) {
    // Remove Comments
    $wp_admin_bar->remove_node( 'comments' );
	
    // Remove New (with all its sub-items)
    $wp_admin_bar->remove_node( 'new-content' );

    // Remove GraphiQL IDE
    $wp_admin_bar->remove_node( 'graphiql-ide' );

    // Remove Notes (Elementor Notes)
    $wp_admin_bar->remove_node( 'elementor_notes' );
	
	// Remove Edit
    $wp_admin_bar->remove_node( 'edit' );
}