<?php

namespace FontAwesomeElementorAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use FontAwesomeLib\Query_Resolver;
use FontAwesomeLib\Auth_Token_Provider;
use FontAwesomeLib\Crypto;
use WP_Error;

class Compatibility {
	/**
	 * Minimum PHP Version
	 *
	 * @since 0.1.0
	 * @var string Minimum PHP version required to run the addon.
	 */
	const MINIMUM_PHP_VERSION = '7.4';

	/**
	 * Checks if the environment is compatible for setting up a Font Awesome Kit
	 * for self-hosting and use in the Elementor editor.
	 *
	 * @return bool|WP_Error True if compatible, WP_Error with details if not.
	 */
	public static function is_compatible_for_setup(): bool|WP_Error {
		$error = new WP_Error();

		$result = self::check_compatibility_php();

		if ( is_wp_error( $result ) ) {
			$error->merge_from( $result );
		}

		$result = self::check_compatibility_wp_filesystem();

		if ( is_wp_error( $result ) ) {
			$error->merge_from( $result );
		}

		global $wp_filesystem;

		$result = self::check_compatibility_wp_upload_dir( $wp_filesystem );

		if ( is_wp_error( $result ) ) {
			$error->merge_from( $result );
		}

		$result = self::check_compatibility_temp_dir( $wp_filesystem );

		if ( is_wp_error( $result ) ) {
			$error->merge_from( $result );
		}

		$result = self::check_compatibility_api_service();

		if ( is_wp_error( $result ) ) {
			$error->merge_from( $result );
		}

		if ( defined( 'LOGGED_IN_KEY' ) && is_string( LOGGED_IN_KEY )
			&& defined( 'LOGGED_IN_SALT' ) && is_string( LOGGED_IN_SALT ) ) {
			$crypto = new Crypto( [
				'key' => LOGGED_IN_KEY,
				'salt' => LOGGED_IN_SALT,
			] );

			$result = $crypto->is_compatible();

			if ( is_wp_error( $result ) ) {
				$error->merge_from( $result );
			}
		} else {
			$error->merge_from( new WP_Error(
				'fontawesome_elementor_addon_compatibility_crypto_setup_error',
				sprintf(
					/* translators: 1: Plugin name */
					__( '%1$s requires the WordPress secret keys LOGGED_IN_KEY and LOGGED_IN_SALT to be defined so it can securely store your API token. One or both are missing from your site configuration.', 'fontawesome-elementor-addon' ),
					esc_html__( 'Font Awesome Elementor Addon', 'fontawesome-elementor-addon' )
				)
			) );
		}

		// $error starts as an empty WP_Error (see above), so it carries a message
		// only when one of the checks above merged a failure into it. Any single
		// failure therefore means the environment is incompatible — hence >= 1.
		// (Contrast is_compatible_for_editing(), which seeds $error with a
		// baseline message and so correctly uses > 1.)
		if ( count( $error->get_error_messages() ) >= 1 ) {
			return $error;
		} else {
			return true;
		}
	}

	/**
	 * Checks if the environment is compatible for editing in Elementor.
	 *
	 * @return bool|WP_Error True if compatible, WP_Error with details if not.
	 */
	public static function is_compatible_for_editing(): bool|WP_Error {
		$error = new WP_Error(
			'fontawesome_elementor_addon_compatibility_editing_error',
			sprintf(
				/* translators: 1: Plugin name */
				__( '%1$s may not function properly in the Elementor editor due to compatibility issues with your WordPress hosting environment. Please review the compatibility requirements and ensure your environment meets them for the best experience.', 'fontawesome-elementor-addon' ),
				esc_html__( 'Font Awesome Elementor Addon', 'fontawesome-elementor-addon' )
			)
		);

		$result = self::check_compatibility_php();

		if ( is_wp_error( $result ) ) {
			$error->merge_from( $result );
		}

		$result = self::check_compatibility_wp_filesystem();

		if ( is_wp_error( $result ) ) {
			$error->merge_from( $result );
		}

		if ( is_wp_error( $error ) && count( $error->get_error_messages() ) > 1 ) {
			return $error;
		}

		return true;
	}

	private static function check_compatibility_api_service(): bool|WP_Error {
		$query_resolver = new Query_Resolver();
		$auth_token_provider = new Auth_Token_Provider( 'FAKE_API_TOKEN' );
		$query = <<<'EOT'
			query {
				release(version: "7.x") {
					version
				}
			}
			EOT;

		$response = $query_resolver->query( [ 'query' => $query ], $auth_token_provider, [ 'ignore_auth' => true ] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'fontawesome_elementor_addon_compatibility_api_service',
				sprintf(
					/* translators: 1: Plugin name */
					__( '%1$s requires that your WordPress site can access the Font Awesome API service.', 'fontawesome-elementor-addon' ),
					esc_html__( 'Font Awesome Elementor Addon', 'fontawesome-elementor-addon' )
				)
			);
		}

		return true;
	}

	private static function check_compatibility_wp_filesystem(): bool|WP_Error {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem( false ) ) {
			return new WP_Error(
				'fontawesome_elementor_addon_compatibility_wp_filesystem_error',
				sprintf(
					/* translators: 1: Plugin name */
					__( 'There was an error initializing the WP Filesystem to access the uploads directory. %1$s requires that your WordPress site is configured to allow reading and writing files using WP_Filesystem.', 'fontawesome-elementor-addon' ),
					esc_html__( 'Font Awesome Elementor Addon', 'fontawesome-elementor-addon' )
				)
			);
		}

		return true;
	}

	private static function check_compatibility_wp_upload_dir( $wp_filesystem ): bool|WP_Error {
		$upload_dir = wp_upload_dir( null, false, false );

		if ( ! is_array( $upload_dir ) || ( isset( $upload_dir['error'] ) && false !== $upload_dir['error'] ) || ! isset( $upload_dir['basedir'] ) || ! isset( $upload_dir['baseurl'] ) || ! $wp_filesystem->is_dir( $upload_dir['basedir'] ) || ! $wp_filesystem->is_writable( $upload_dir['basedir'] ) ) {
			return new WP_Error(
				'fontawesome_elementor_addon_compatibility_wp_upload_dir',
				sprintf(
					/* translators: 1: Plugin name */
					__( '%1$s requires that your WordPress site is configured to allow writing files under wp_upload_dir.', 'fontawesome-elementor-addon' ),
					esc_html__( 'Font Awesome Elementor Addon', 'fontawesome-elementor-addon' )
				)
			);
		}

		return true;
	}

	private static function check_compatibility_temp_dir( $wp_filesystem ): bool|WP_Error {
		// Check for temp dir write access.
		$base_temp_dir = get_temp_dir();

		$temp_dir =
			$base_temp_dir .
			'fontawesome-elementor-addon-' .
			wp_generate_uuid4() .
			'/';

		$was_temp_dir_created = $wp_filesystem->mkdir( $temp_dir );

		if ( ! $was_temp_dir_created ) {
			return new WP_Error(
				'fontawesome_elementor_addon_compatibility_temp_dir',
				sprintf(
					/* translators: 1: Plugin name */
					__( '%1$s requires that your WordPress site is configured to allow creating temporary files and directories.', 'fontawesome-elementor-addon' ),
					esc_html__( 'Font Awesome Elementor Addon', 'fontawesome-elementor-addon' )
				)
			);
		}

		try {
			$wp_filesystem->delete( $temp_dir, true );
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Intentionally ignoring delete errors for temp-dir cleanup: failure here should not block compatibility checks.
		}

		return true;
	}

	private static function check_compatibility_php(): bool|WP_Error {
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			return new WP_Error(
				'fontawesome_elementor_addon_compatibility_minimum_php_version_error',
				sprintf(
					/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
					__( '%1$s requires %2$s version %3$s or greater.', 'fontawesome-elementor-addon' ),
					esc_html__( 'Font Awesome Elementor Addon', 'fontawesome-elementor-addon' ),
					esc_html( 'PHP' ),
					esc_html( self::MINIMUM_PHP_VERSION )
				)
			);
		}

		return true;
	}
}
