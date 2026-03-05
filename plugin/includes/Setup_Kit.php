<?php

namespace FontAwesomeElementorAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use FontAwesomeLib\Query_Resolver;
use FontAwesomeLib\Auth_Token_Provider;
use FontAwesomeLib\Kit_Download;
use FontAwesomeElementorAddon\Options;

class Setup_Kit {
	/**
	 * Check if a kit has already been set up by checking if the kit assets directory exists and is valid.
	 */
	public static function has_kit_been_set_up( $kit_token ): bool {
		return false;
	}

	public static function start(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
			return;
		}

		check_ajax_referer( 'fontawesome_elementor_addon_kit_setup_nonce', 'nonce' );

		if ( ! Compatibility::is_compatible_for_setup() ) {
			wp_send_json_error([
				'message' =>
				__( 'Font Awesome Elementor Addon is not compatible on this site.', 'fontawesome-elementor-addon' ),
			], 500);

			return;
		}

		$api_token = Options::get_decrypted_api_token();

		if ( is_wp_error( $api_token ) ) {
			wp_send_json_error([
				'message' =>
				$api_token->get_error_message(),
			], 500);
		}

		$option = get_option( Options::option_name(), [] );

		if ( ! is_array( $option ) || ! isset( $option['kit_token'] ) || ! is_string( $option['kit_token'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid Font Awesome kit token. Try re-setting it.', 'fontawesome-elementor-addon' ) ], 500 );
			return;
		}

		$kit_token = $option['kit_token'];

		$token_provider = new Auth_Token_Provider( $api_token );
		$access_token = $token_provider->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			wp_send_json_error([
				'message' =>
				$access_token->get_error_message(),
			], 500);

			return;
		}

		$query_resolver = new Query_Resolver();

		$kit_download = Kit_Download::create_kit_download( $query_resolver, $token_provider, $kit_token );

		if ( is_wp_error( $kit_download ) ) {
			wp_send_json_error([
				'message' =>
				$kit_download->get_error_message(),
			], 500);

			return;
		}

		wp_send_json_success( [ 'build_id' => $kit_download->get_build_id() ] );

		return;
	}

	public static function status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		check_ajax_referer( 'fontawesome_elementor_addon_kit_setup_nonce', 'nonce' );

		$build_id = isset( $_POST['build_id'] ) ? sanitize_text_field( wp_unslash( $_POST['build_id'] ) ) : '';

		if ( ! $build_id || '' === $build_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing build_id', 'fontawesome-elementor-addon' ) ], 400 );
			return;
		}

		$option = get_option( Options::option_name(), [] );

		if ( ! is_array( $option ) || ! isset( $option['kit_token'] ) || ! is_string( $option['kit_token'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid Font Awesome kit token. Try re-setting it.', 'fontawesome-elementor-addon' ) ], 500 );
			return;
		}

		$api_token = Options::get_decrypted_api_token();

		if ( is_wp_error( $api_token ) ) {
			wp_send_json_error([
				'message' =>
				$api_token->get_error_message(),
			], 500);
		}

		$upload_dir = \wp_upload_dir( null, false, false );

		if ( isset( $upload_dir['error'] ) && false !== $upload_dir['error'] ) {
			wp_send_json_error([
				'message' =>
				__( 'There was an error initializing the uploads directory for setting up the Font Awesome Kit', 'fontawesome-elementor-addon' ),
			], 500);
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

			wp_send_json_error( [ 'message' => $message ], 500 );

			return;
		}

		if ( ! $kit_download->is_ready() ) {
			wp_send_json_success( [ 'ready' => false ] );
			return;
		}

		$upload_base_dir = $upload_dir['basedir'];

		$kit_assets_absolute_dir = $kit_download->download_and_prepare_selfhosting( $query_resolver, $token_provider, $upload_base_dir );

		if ( is_wp_error( $kit_assets_absolute_dir ) ) {
			$kit_assets_absolute_dir->add(
				'fontawesome_elementor_addon_download_kit_error',
				__(
					'Font Awesome Elementor Addon was unable to download and prepare the Font Awesome Kit for self-hosting.',
					'fontawesome-elementor-addon',
				)
			);

			$message = implode( ' ', $kit_assets_absolute_dir->get_error_messages() );

			wp_send_json_error( [ 'message' => $message ], 500 );

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

		$update_result = update_option( Options::option_name(), $option );

		if ( false === $update_result ) {
			$previous_option = get_option( Options::option_name() );

			// Don't include the api_token in the comparison.
			unset( $previous_option['api_token'] );

			if ( $previous_option != $option ) {
				wp_send_json_error( [
					'message' =>
											__( 'Your kit was successfully downloaded and set up on your WordPress server, but there was a problem updating the plugin options with the results. Try again?', 'fontawesome-elementor-addon' ),
				], 500 );

				return;
			}
		}

		wp_send_json_success( [ 'done' => true, 'last_kit_refresh_at' => $last_kit_refresh_at ] );

		return;
	}
}
