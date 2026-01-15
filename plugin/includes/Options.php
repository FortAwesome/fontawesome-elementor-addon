<?php

namespace FontAwesomeElementorAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use \WP_Error;
use FontAwesomeLib\Crypto;

class Options {
	private const OPTIONS_KEY = 'fontawesome_elementor_addon';

	/**
	 * Return the name of this plugin's options in the database.
	 *
	 * @return string the option name
	 */
	public static function options_key(): string {
		return self::OPTIONS_KEY;
	}

	/**
	 * Decrypt and return the encrypted API token from the options.
	 * @return string|WP_Error the decrypted API token on success or WP_Error on error.
	 */
	public static function get_decrypted_api_token(): string|WP_Error {
 		$option = get_option(Options::options_key(), []);

    	$api_token = null;

        if ( !is_array( $option ) || !isset( $option['api_token'] ) || !is_string( $option['api_token'] ) || '' === $option['api_token'] ) {
        	return new WP_Error(
         		"fontawesome_elementor_addon_options_missing_api_token_error",
           		__('No API Token found in options. Try re-setting it.', 'fontawesome-elementor-addon')
         	);
        }

        $encrypted_api_token = $option['api_token'];

        if ( !defined( 'LOGGED_IN_SALT' )
        	|| !is_string( LOGGED_IN_SALT )
         	|| !defined( 'LOGGED_IN_KEY' )
          	|| !is_string( LOGGED_IN_KEY ) ) {
           return new WP_Error(
         		"fontawesome_elementor_addon_options_crypto_setup_error",
           		__('This site is not configured for encryption. Missing LOGGED_IN_SALT or LOGGED_IN_KEY.', 'fontawesome-elementor-addon')

           );
         }

         $crypto = new Crypto(["key" => LOGGED_IN_KEY, "salt" => LOGGED_IN_SALT]);

         $api_token = $crypto->decrypt( $encrypted_api_token );

         if( is_wp_error( $api_token ) ) {
         	return new WP_Error(
        		"fontawesome_elementor_addon_options_decrypt_error",
          		__('Failed decrypting your Font Awesome API token. Maybe your WordPress secrets have changed since you set it. Try re-setting it.', 'fontawesome-elementor-addon')
            );
         }

         return $api_token;
	}
}
