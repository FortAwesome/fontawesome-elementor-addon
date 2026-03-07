<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$vendor_dir = trailingslashit( __DIR__ ) . 'vendor';

$vendor_autoload_path = trailingslashit( $vendor_dir ) . 'autoload.php';

if ( file_exists( $vendor_autoload_path ) ) {
	require_once $vendor_autoload_path;
}
