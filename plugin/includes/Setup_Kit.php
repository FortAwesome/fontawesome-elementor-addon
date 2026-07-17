<?php

namespace FontAwesomeElementorAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use FontAwesomeLib\Query_Resolver;
use FontAwesomeLib\Auth_Token_Provider;
use FontAwesomeLib\Kit_Download;
use FontAwesomeElementorAddon\Options;
use WP_Error;
use WP_Filesystem;

class Setup_Kit {
	/**
	 * Check if a kit has already been set up by checking if the kit assets directory exists and is valid.
	 * for the currently configured kit token and build id.
	 *
	 * @return bool|WP_Error True if a kit has been set up, WP_Error if there was an error checking the kit setup status.
	 */
	public static function has_kit_been_set_up(): bool|WP_Error {
		$option = \get_option( Options::option_name(), [] );
		$kit_assets_relative_dir = $option['kit_assets_relative_dir'] ?? null;
		$kit_token = $option['kit_token'] ?? null;
		$build_id = $option['build_id'] ?? null;

		if ( ! is_string( $kit_assets_relative_dir ) || '' === $kit_assets_relative_dir
			|| ! is_string( $kit_token ) || '' === $kit_token
			|| ! is_string( $build_id ) || '' === $build_id ) {
			return false;
		}

		$upload_base_dir = self::get_upload_base_dir();

		if ( \is_wp_error( $upload_base_dir ) ) {
			return $upload_base_dir;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem( false ) ) {
			return new WP_Error(
				'fontawesome_elementor_addon_wp_filesystem_error',
				__(
					'There was an error initializing the WP Filesystem to access the uploads directory.',
					'fontawesome-elementor-addon'
				),
			);
		}

		global $wp_filesystem;

		$kit_json_path = trailingslashit( $upload_base_dir ) . trailingslashit( $kit_assets_relative_dir ) . 'metadata/kit.json';

		$kit_json = $wp_filesystem->get_contents( $kit_json_path );

		if ( false === $kit_json ) {
			return false;
		}

		$data = json_decode( $kit_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		$metadata_build_id = $data['build_id'] ?? null;
		$metadata_kit_token = $data['token'] ?? null;

		return is_string( $build_id ) && is_string( $kit_token ) && $build_id === $metadata_build_id && $kit_token === $metadata_kit_token;
	}

	public static function start(): void {
		try {
			self::handle_start();
		} catch ( \Throwable $e ) {
			self::send_throwable( $e );
		}
	}

	private static function handle_start(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::send_error( 'Forbidden', 403 );
			return;
		}

		check_ajax_referer( 'fontawesome_elementor_addon_kit_setup_nonce', 'nonce' );

		$compatibility = Compatibility::is_compatible_for_setup();

		if ( \is_wp_error( $compatibility ) ) {
			self::send_error( $compatibility, 500 );
			return;
		}

		$api_token = Options::get_decrypted_api_token();

		if ( is_wp_error( $api_token ) ) {
			self::send_error( $api_token, 500 );
			return;
		}

		$option = get_option( Options::option_name(), [] );

		if ( ! is_array( $option ) || ! isset( $option['kit_token'] ) || ! is_string( $option['kit_token'] ) ) {
			self::send_error( __( 'Invalid Font Awesome kit token. Try re-setting it.', 'fontawesome-elementor-addon' ), 500 );
			return;
		}

		$kit_token = $option['kit_token'];

		$token_provider = new Auth_Token_Provider( $api_token );
		$access_token = $token_provider->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			self::send_error( $access_token, 500 );
			return;
		}

		$query_resolver = new Query_Resolver();

		$kit_download = Kit_Download::create_kit_download( $query_resolver, $token_provider, $kit_token );

		if ( is_wp_error( $kit_download ) ) {
			self::send_error( $kit_download, 500 );
			return;
		}

		wp_send_json_success( [ 'build_id' => $kit_download->get_build_id() ] );
	}

	public static function status() {
		try {
			self::handle_status();
		} catch ( \Throwable $e ) {
			self::send_throwable( $e );
		}
	}

	private static function handle_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::send_error( 'Forbidden', 403 );
			return;
		}

		check_ajax_referer( 'fontawesome_elementor_addon_kit_setup_nonce', 'nonce' );

		$build_id = isset( $_POST['build_id'] ) ? sanitize_text_field( wp_unslash( $_POST['build_id'] ) ) : '';

		if ( ! $build_id || '' === $build_id ) {
			self::send_error( __( 'Missing build_id', 'fontawesome-elementor-addon' ), 400 );
			return;
		}

		$option = get_option( Options::option_name(), [] );

		if ( ! is_array( $option ) || ! isset( $option['kit_token'] ) || ! is_string( $option['kit_token'] ) ) {
			self::send_error( __( 'Invalid Font Awesome kit token. Try re-setting it.', 'fontawesome-elementor-addon' ), 500 );
			return;
		}

		$api_token = Options::get_decrypted_api_token();

		if ( is_wp_error( $api_token ) ) {
			self::send_error( $api_token, 500 );
			return;
		}

		$upload_base_dir = self::get_upload_base_dir();

		if ( is_wp_error( $upload_base_dir ) ) {
			self::send_error( $upload_base_dir, 500 );
			return;
		}

		$kit_download = new Kit_Download(
			$option['kit_token'],
			$build_id
		);

		$token_provider = new Auth_Token_Provider( $api_token );

		$query_resolver = new Query_Resolver();

		$poll_result = $kit_download->poll( $query_resolver, $token_provider );

		if ( is_wp_error( $poll_result ) ) {
			$this_message = __(
				'Font Awesome Elementor Addon was unable to poll the Kit Download status.',
				'fontawesome-elementor-addon',
			);

			$that_message = $poll_result->get_error_message();

			$message = $that_message . ' ' . $this_message;

			self::send_error( $message, 500 );

			return;
		}

		if ( ! $kit_download->is_ready() ) {
			wp_send_json_success( [ 'ready' => false ] );
			return;
		}

		$kit_assets_absolute_dir = $kit_download->download_and_prepare_selfhosting( $query_resolver, $token_provider, $upload_base_dir );

		if ( is_wp_error( $kit_assets_absolute_dir ) ) {
			$kit_assets_absolute_dir->add(
				'fontawesome_elementor_addon_download_kit_error',
				__(
					'Font Awesome Elementor Addon was unable to download and prepare the Font Awesome Kit for self-hosting.',
					'fontawesome-elementor-addon',
				)
			);

			self::send_error( $kit_assets_absolute_dir, 500 );

			return;
		}

		$kit_assets_relative_dir = str_replace( trailingslashit( $upload_base_dir ), '', trailingslashit( $kit_assets_absolute_dir ) );

		$option = Options::get_options_with_defaults();

		// We don't want to re-encrypt it.
		unset( $option['api_token'] );

		$option['kit_assets_relative_dir'] = $kit_assets_relative_dir;
		$option['build_id'] = $kit_download->get_build_id();
		$last_kit_refresh_at = time();
		$option['last_kit_refresh_at'] = $last_kit_refresh_at;
		$last_kit_refresh_at_formatted = Options::format_unix_timestamp( $last_kit_refresh_at );

		$update_result = update_option( Options::option_name(), $option );

		if ( false === $update_result ) {
			$previous_option = get_option( Options::option_name() );

			// Don't include the api_token in the comparison.
			unset( $previous_option['api_token'] );

			if ( $previous_option !== $option ) {
				self::send_error(
					__( 'Your kit was successfully downloaded and set up on your WordPress server, but there was a problem updating the plugin options with the results. Try again?', 'fontawesome-elementor-addon' ),
					500
				);

				return;
			}
		}

		wp_send_json_success( [
			'done' => true,
			'last_kit_refresh_at_formatted' => $last_kit_refresh_at_formatted,
		] );
	}

	private static function get_upload_base_dir(): string|WP_Error {
		$upload_dir = \wp_upload_dir( null, false, false );

		if ( isset( $upload_dir['error'] ) && false !== $upload_dir['error'] ) {
			return new WP_Error(
				'fontawesome_elementor_addon_upload_dir_error',
				__(
					'There was an error initializing the uploads directory for setting up the Font Awesome Kit',
					'fontawesome-elementor-addon'
				),
			);
		}

		return $upload_dir['basedir'];
	}

	/**
	 * Send a normalized JSON error response and log it server-side.
	 *
	 * Accepts a WP_Error, a plain string, or an array with a 'message' key, and
	 * always emits `data` as a list of { code, message } objects so the client
	 * can render every error consistently (previously, plain-array errors were
	 * silently dropped by the front end because they were not a list). Server
	 * errors (HTTP >= 500) are also written to the PHP error log so a diagnostic
	 * trace survives even when the UI cannot show it. Client errors (4xx such as
	 * a 403 "Forbidden" or a 400 "Missing build_id") are expected, actionable by
	 * the caller, and would only be log noise, so they are not logged.
	 *
	 * @param WP_Error|string|array $error  The error to report.
	 * @param int                   $status HTTP status code.
	 */
	private static function send_error( $error, int $status = 500 ): void {
		$errors = self::normalize_errors( $error );

		if ( $status >= 500 ) {
			foreach ( $errors as $entry ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf(
					'[fontawesome-elementor-addon] kit setup error (%s): %s',
					$entry['code'],
					$entry['message']
				) );
			}
		}

		wp_send_json_error( $errors, $status );
	}

	/**
	 * Normalize an error of various shapes into a list of { code, message } entries.
	 *
	 * @param WP_Error|string|array $error The error to normalize.
	 * @return array<int, array{code: string, message: string}>
	 */
	private static function normalize_errors( $error ): array {
		if ( \is_wp_error( $error ) ) {
			$entries = [];

			foreach ( $error->get_error_codes() as $code ) {
				foreach ( $error->get_error_messages( $code ) as $message ) {
					$entries[] = [
						'code' => (string) $code,
						'message' => (string) $message,
					];
				}
			}

			if ( ! empty( $entries ) ) {
				return $entries;
			}
		}

		if ( is_string( $error ) && '' !== $error ) {
			return [
				[
					'code' => 'error',
					'message' => $error,
				],
			];
		}

		if ( is_array( $error ) && isset( $error['message'] ) && is_string( $error['message'] ) ) {
			return [
				[
					'code' => ( isset( $error['code'] ) && is_string( $error['code'] ) ) ? $error['code'] : 'error',
					'message' => $error['message'],
				],
			];
		}

		return [
			[
				'code' => 'unknown_error',
				'message' => __( 'An unknown error occurred.', 'fontawesome-elementor-addon' ),
			],
		];
	}

	/**
	 * Convert an uncaught Throwable into a logged, normalized JSON error response.
	 *
	 * Without this, an uncaught error/exception in an AJAX handler becomes a bare
	 * HTTP 500 with no JSON body (or a WSOD HTML page), which the front end
	 * silently swallows. This guarantees a full server-side log entry with stack
	 * trace and a user-visible message.
	 *
	 * @param \Throwable $e The uncaught throwable.
	 *
	 * @throws \Throwable Re-thrown when $e is one of WordPress's own die/exit
	 *                    signals (WPDieException, WPAjaxDieStopException,
	 *                    WPAjaxDieContinueException), so normal response
	 *                    termination is not swallowed.
	 */
	private static function send_throwable( \Throwable $e ): void {
		// Let WordPress's own die/exit signals propagate (e.g. from wp_send_json_*
		// under the PHPUnit ajax test harness). In production wp_die() exits, so
		// this catch is never reached for a normal response.
		if ( $e instanceof \WPDieException
			|| $e instanceof \WPAjaxDieStopException
			|| $e instanceof \WPAjaxDieContinueException ) {
			throw $e;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf(
			"[fontawesome-elementor-addon] uncaught %s during kit setup: %s in %s:%d\n%s",
			get_class( $e ),
			$e->getMessage(),
			$e->getFile(),
			$e->getLine(),
			$e->getTraceAsString()
		) );

		$message = __( 'An unexpected error occurred during kit setup. Check your server error log for details.', 'fontawesome-elementor-addon' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$message .= sprintf( ' [%s] %s', get_class( $e ), $e->getMessage() );
		}

		self::send_error( $message, 500 );
	}
}
