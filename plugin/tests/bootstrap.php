<?php
/**
 * PHPUnit bootstrap for the Font Awesome Elementor Addon integration test suite.
 *
 * Designed to run inside the wp-env `tests-cli` container, where the WordPress
 * PHPUnit test library is available at WP_TESTS_DIR (default /wordpress-phpunit).
 * Use the supported entry point:
 *
 *     bin/test
 *
 * which wraps `wp-env run tests-cli … vendor/bin/phpunit`.
 *
 * @package FontAwesomeElementorAddon
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	$_tests_dir = '/wordpress-phpunit';
}

$_functions = $_tests_dir . '/includes/functions.php';

if ( ! file_exists( $_functions ) ) {
	fwrite( STDERR, "Could not find the WordPress test library at {$_tests_dir}.\n" );
	fwrite( STDERR, "Run the suite inside the wp-env tests-cli container (see bin/test).\n" );
	exit( 1 );
}

// Composer autoloader: PHPUnit polyfills, WP test utils, the plugin's own PSR-4
// classes, and the bundled Font Awesome library.
require dirname( __DIR__ ) . '/vendor/autoload.php';

// Give access to tests_add_filter() before WordPress loads.
require_once $_functions;

// Load the plugin so its AJAX hooks are registered against the running WordPress.
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/fontawesome-elementor-addon.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
