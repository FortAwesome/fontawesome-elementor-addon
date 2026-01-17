<?php

/**
 * Plugin Name:                Font Awesome Elementor Addon
 * Plugin URI:                 https://fontawesome.com/
 * Description:                Add Font Awesome Pro icons to Elementor.
 * Version:                    0.0.1
 * Author:                     Font Awesome
 * Author URI:                 https://fontawesome.com/
 * License:                    GPLv3
 * Text Domain:                fontawesome-elementor-addon
 * Requires Plugins:           elementor
 * Elementor tested up to:     3.34.1
 * Elementor Pro tested up to: 3.34.1
 */

defined( 'WPINC' ) || die();

add_action('elementor/init', function () {
	require_once __DIR__ . '/autoload.php';
	\FontAwesomeElementorAddon\Plugin::instance()->init();
});

add_action('admin_enqueue_scripts', function( $hook ) {
	require_once __DIR__ . '/autoload.php';
	if ( $hook !== 'settings_page_' . \FontAwesomeElementorAddon\Settings_Page::PAGE_SLUG ) {
		return;
	}

	wp_enqueue_script(
		'fontawesome-elementor-addon-admin',
		plugins_url( 'assets/js/admin.js', __FILE__ ),
		[ 'jquery' ],
		\FontAwesomeElementorAddon\Plugin::PLUGIN_VERSION,
		true
	);

	wp_localize_script(
		'fontawesome-elementor-addon-admin',
		'FontawesomeElementorAddonAdmin',
		[
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'fontawesome_elementor_addon_kit_setup_nonce' ),
		]
	);
});

add_action( 'wp_ajax_fontawesome_elementor_addon_kit_setup_start', [ '\FontAwesomeElementorAddon\Setup_Kit', 'start' ] );
add_action( 'wp_ajax_fontawesome_elementor_addon_kit_setup_status', [ '\FontAwesomeElementorAddon\Setup_Kit', 'status' ] );
