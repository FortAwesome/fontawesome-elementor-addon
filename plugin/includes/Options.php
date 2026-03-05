<?php

namespace FontAwesomeElementorAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;
use FontAwesomeLib\Crypto;

class Options {
	private const OPTION_NAME = 'fontawesome_elementor_addon';
	private const OPTION_SCHEMA_VERSION = 1;
	private const BACKEND_ONLY_SETTINGS_TEXT = [ 'kit_assets_relative_dir', 'option_schema_version' ];

	/**
	 * Return the option_name of this plugin's option record in the `wp_options` table in the database.
	 *
	 * @return string the option name
	 */
	public static function option_name(): string {
		return self::OPTION_NAME;
	}

	/**
	 * Decrypt and return the encrypted API token from the options.
	 *
	 * @return string|WP_Error the decrypted API token on success or WP_Error on error.
	 */
	public static function get_decrypted_api_token(): string|WP_Error {
		$option = get_option( self::option_name(), [] );

		$api_token = null;

		if ( ! is_array( $option ) || ! isset( $option['api_token'] ) || ! is_string( $option['api_token'] ) || '' === $option['api_token'] ) {
			return new WP_Error(
				'fontawesome_elementor_addon_options_missing_api_token_error',
				__( 'No API Token found in options. Try re-setting it.', 'fontawesome-elementor-addon' )
			);
		}

		$encrypted_api_token = $option['api_token'];

		if ( ! defined( 'LOGGED_IN_SALT' )
			|| ! is_string( LOGGED_IN_SALT )
			|| ! defined( 'LOGGED_IN_KEY' )
			|| ! is_string( LOGGED_IN_KEY ) ) {
			return new WP_Error(
				'fontawesome_elementor_addon_options_crypto_setup_error',
				__( 'This site is not configured for encryption. Missing LOGGED_IN_SALT or LOGGED_IN_KEY.', 'fontawesome-elementor-addon' )
			);
		}

		$crypto = new Crypto( [
			'key' => LOGGED_IN_KEY,
			'salt' => LOGGED_IN_SALT,
		] );

		$api_token = $crypto->decrypt( $encrypted_api_token );

		if ( is_wp_error( $api_token ) ) {
			return new WP_Error(
				'fontawesome_elementor_addon_options_decrypt_error',
				__( 'Failed decrypting your Font Awesome API token. Maybe your WordPress secrets have changed since you set it. Try re-setting it.', 'fontawesome-elementor-addon' )
			);
		}

		return $api_token;
	}

	/**
	 * Get options from database, with defaults. This can also be used to initialize options
	 *  with defaults before writing the first time.
	 *
	 * @return array
	 */
	public static function get_options_with_defaults(): array {
		$defaults = [
			'kit_token' => '',
			'api_token' => '',
			'build_id' => '',
			'last_kit_refresh_at' => null,
			'load'  => 0,
			'option_schema_version' => self::OPTION_SCHEMA_VERSION,
		];

		$saved = get_option( self::option_name(), [] );

		return wp_parse_args( is_array( $saved ) ? $saved : [], $defaults );
	}

	/**
	 * Sanitize the input options array.
	 *
	 * @param array $input
	 * @return array sanitized options that could be written to the database.
	 */
	public static function sanitize( $input ): array {
		// Existing saved settings (may include keys not on the form)
			$existing = get_option( self::option_name(), [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

			$output = $existing;

		foreach ( self::BACKEND_ONLY_SETTINGS_TEXT as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$output[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		if ( array_key_exists( 'kit_token', $input ) ) {
			$output['kit_token'] = sanitize_text_field( wp_unslash( $input['kit_token'] ) );
		}

		if ( array_key_exists( 'build_id', $input ) ) {
			$output['build_id'] = sanitize_text_field( wp_unslash( $input['build_id'] ) );
		}

		if ( array_key_exists( 'last_kit_refresh_at', $input ) && is_int( $input['last_kit_refresh_at'] ) ) {
			$output['last_kit_refresh_at'] = $input['last_kit_refresh_at'];
		}

		if ( array_key_exists( 'api_token', $input ) ) {
			if ( defined( 'LOGGED_IN_SALT' ) &&
			is_string( LOGGED_IN_SALT ) &&
			defined( 'LOGGED_IN_KEY' ) &&
			is_string( LOGGED_IN_KEY ) ) {
				$crypto = new Crypto( [
					'key' => LOGGED_IN_KEY,
					'salt' => LOGGED_IN_SALT,
				] );
				$sanitized = sanitize_text_field( wp_unslash( $input['api_token'] ) );
				$encrypted = $crypto->encrypt( $sanitized );
				if ( ! is_wp_error( $encrypted ) ) {
						$output['api_token'] = $encrypted;
				}
			}
		}

		if ( array_key_exists( 'load', $input ) ) {
			$output['load'] = ! empty( $input['load'] ) ? 1 : 0;
		}

			return $output;
	}

	/**
	 * Format a unix timestamp into a human-readable date/time string, using the site's configured date and time formats and timezone.
	 *
	 * @param int $unix_ts Unix timestamp.
	 * @return string Formatted date/time string.
	 */
	public static function format_unix_timestamp(int $unix_ts): string {
	    $date = (new \DateTimeImmutable())->setTimestamp($unix_ts);

	    // Convert to the site's configured timezone
	    $local_date = $date->setTimezone(\wp_timezone());

	    // Format using the site's date/time format settings
	    $format = \get_option('date_format') . ' ' . \get_option('time_format');

	    return $local_date->format($format);
	}
}
