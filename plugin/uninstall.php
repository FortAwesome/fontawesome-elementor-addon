<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	// If uninstall.php is not called by WordPress, die.
	die;
}

require_once trailingslashit( __DIR__ ) . 'includes/Options.php';

delete_option( FontAwesomeElementorAddon\Options::option_name() );

// For site options in Multisite.
delete_site_option( FontAwesomeElementorAddon\Options::option_name() );
