<?php

namespace FontAwesomeElementorAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use FontAwesomeElementorAddon\Setup_Kit;
use FontAwesomeLib\Crypto;

class Settings_Page {
	const PAGE_SLUG = 'fontawesome-elementor-addon-settings';
	const SETTINGS_GROUP = 'fontawesome_elementor_addon_settings_group';

	/**
	 * Instance
	 *
	 * @since 0.1.0
	 * @access private
	 * @static
	 * @var \FontAwesomeElementorAddon\Settings The single instance of the class.
	 */
	private static $_instance = null;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @since 0.1.0
	 * @access public
	 * @static
	 * @return \FontAwesomeElementorAddon\Settings An instance of the class.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {
		/* noop */ }

	public function init() {
		add_action( 'admin_menu', fn () => $this->add_menu() );
		add_action( 'admin_init', fn () => $this->register_settings() );
		add_filter( 'pre_update_option_' . Options::option_name(), fn ( $new_value, $old_value ) => $this->pre_update_encrypt_api_token( $new_value, $old_value ), 10, 2 );
	}

	private function add_menu() {
		add_options_page(
			'Font Awesome Elementor Settings',
			'Font Awesome Elementor Addon',
			'manage_options',
			self::PAGE_SLUG,
			fn () => $this->render_page()
		);
	}

	private function register_settings() {
		$general_section_name = 'fontawesome_elementor_addon_general_section';

		register_setting(
			self::SETTINGS_GROUP,
			Options::option_name(),
			[ '\FontAwesomeElementorAddon\Options', 'sanitize' ]
		);

		add_settings_section(
			$general_section_name,
			'',
			function () {
				?>
				<h2 class="fontawesome-elementor-addon-section-title" style="font-size: 2em">Font Awesome Elementor Addon</h1>
				<p>
				<?php echo esc_html__( 'Easily use Font Awesome icons - including Pro! - with Elementor. Just add your API token and Kit token below to get started.', 'fontawesome-elementor-addon' ); ?>
				</p>
				<h2>Add Your Kit Details</h2>
				<p>
				<?php
				printf(
					/* translators: 1: account tokes URL, 2: kits URL */
					esc_html__( 'Visit your <a href="%1$s" target="_blank" rel="noopener noreferrer">Account on fontawesome.com</a> to get your API token, and <a href="%2$s" target="_blank" rel="noopener noreferrer">your Kits</a> to get the token for the Kit you want to use and enter them below.', 'fontawesome-elementor-addon' ),
					esc_attr( 'https://fontawesome.com/account/tokens' ),
					esc_attr( 'https://fontawesome.com/kits' )
				);
				?>
				</p>
				<?php
			},
			self::PAGE_SLUG
		);

		add_settings_field(
			'api_token',
			__( 'API Token', 'fontawesome-elementor-addon' ),
			fn () => $this->render_api_token_field(),
			self::PAGE_SLUG,
			$general_section_name
		);

		add_settings_field(
			'kit_token',
			__( 'Kit Token', 'fontawesome-elementor-addon' ),
			fn () => $this->render_kit_token_field(),
			self::PAGE_SLUG,
			$general_section_name
		);
	}

	public function pre_update_encrypt_api_token( $new_value, $old_value ) {
		if (
			array_key_exists( 'api_token', $new_value ) &&
			is_string( $new_value['api_token'] ) &&
			'' !== $new_value['api_token'] &&
			( ! $old_value || (
			array_key_exists( 'api_token', $old_value ) &&
			$new_value['api_token'] !== $old_value['api_token'] ) ) &&
			defined( 'LOGGED_IN_SALT' ) &&
			is_string( LOGGED_IN_SALT ) &&
			defined( 'LOGGED_IN_KEY' ) &&
			is_string( LOGGED_IN_KEY ) ) {
				$crypto = new Crypto( [
					'key' => LOGGED_IN_KEY,
					'salt' => LOGGED_IN_SALT,
				] );
				$encrypted = $crypto->encrypt( $new_value['api_token'] );
			if ( ! \is_wp_error( $encrypted ) ) {
					$new_value['api_token'] = $encrypted;
			}
		} else {
			// If api_token is not in the new value, it means the user didn't change it. We should keep the old encrypted value.
			$new_value['api_token'] = $old_value['api_token'] ?? Options::INITIAL_API_TOKEN_VALUE;
		}

		return $new_value;
	}

	private function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<form method="post" action="options.php">';
		settings_fields( self::SETTINGS_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';

		$opts = Options::get_options_with_defaults();
		$kit_token = $opts['kit_token'] ?? null;
		$api_token = $opts['api_token'] ?? null;
		$build_id = $opts['build_id'] ?? null;
		$last_kit_refresh_at = $opts['last_kit_refresh_at'] ?? null;
		$is_configured = is_string( $kit_token ) && '' !== $kit_token && is_string( $api_token ) && '' !== $api_token;

		$has_kit_been_set_up_result = Setup_Kit::has_kit_been_set_up();
		$has_kit_been_set_up = \is_wp_error( $has_kit_been_set_up_result ) ? false : $has_kit_been_set_up_result;

		$this->render_kit_setup_section( [
			'is_configured' => $is_configured,
			'has_kit_been_set_up' => $has_kit_been_set_up,
			'last_kit_refresh_at' => $last_kit_refresh_at,
		] );

		echo '</div>';
	}

	private function render_kit_setup_section( $params = [] ) {
		$is_configured = is_array( $params ) ? boolval( $params['is_configured'] ?? false ) : false;
		$is_form_changed = is_array( $params ) ? boolval( $params['is_form_changed'] ?? false ) : false;
		$has_kit_been_set_up = is_array( $params ) ? boolval( $params['has_kit_been_set_up'] ?? false ) : false;
		$last_kit_refresh_at = is_array( $params ) ? ( $params['last_kit_refresh_at'] ?? null ) : null;
		$last_kit_refresh_at_formatted = is_int( $last_kit_refresh_at ) ? Options::format_unix_timestamp( $last_kit_refresh_at ) : null;

		$button_label = ( $is_configured && $has_kit_been_set_up )
			? esc_html__( 'Refresh Setup', 'fontawesome-elementor-addon' )
			: esc_html__( 'Setup Kit', 'fontawesome-elementor-addon' );
		$refresh_button_label = esc_html__( 'Refresh Setup', 'fontawesome-elementor-addon' );
		$setup_button_label = esc_html__( 'Setup Kit', 'fontawesome-elementor-addon' );

		$initial_setup_message = sprintf(
			/* translators: 1: button label */
			__( 'After saving your API and Kit Token above, click "%1$s" to automatically download and install the Kit for self-hosting on your WordPress server.', 'fontawesome-elementor-addon' ),
			$setup_button_label
		);

		$refresh_message = sprintf(
			/* translators: 1: button label */
			__( 'After changing the Kit Token above or modifying your Kit, click "%1$s" to update the Kit\'s self-hosting on your WordPress server.', 'fontawesome-elementor-addon' ),
			$refresh_button_label
		);

		$concluding_message = __( "Once it's done, the Elementor Icon Library will reflect the changes.", 'fontawesome-elementor-addon' );
		?>
	<h2><?php echo esc_html__( 'Kit Setup', 'fontawesome-elementor-addon' ); ?></h2>
	<div class="fontawesome-elementor-addon-kit-setup">
		<div>
		<p>
		<?php
		if ( $is_configured && $has_kit_been_set_up ) {
			echo esc_html( $refresh_message );
		} else {
			echo esc_html( $initial_setup_message );
		}

			echo ' ' . esc_html( $concluding_message );
		?>
		</p>
		<button
			type="button"
			class="button button-secondary"
			id="fontawesome-elementor-addon-kit-setup-start"
			<?php echo ! $is_configured ? 'disabled' : ''; ?>
		>
		<?php if ( $is_configured && $has_kit_been_set_up ) : ?>
			<?php echo esc_html( $refresh_button_label ); ?>
		<?php else : ?>
			<?php echo esc_html( $setup_button_label ); ?>
		<?php endif; ?>
		</button>

		<span class="spinner" id="fontawesome-elementor-addon-kit-setup-spinner" style="float:none;"></span>

		<span id="fontawesome-elementor-addon-kit-setup-status" style="margin-left:8px;"></span>
		</div>
		<div style="margin-top: 1em; color: #797979; font-size: smaller;">
			Last refreshed at: <span id="fontawesome-elementor-addon-last-kit-refresh-at">
				<?php echo esc_html( $last_kit_refresh_at_formatted ? $last_kit_refresh_at_formatted : 'never' ); ?>
			</span>
		</div>
		<div id="fontawesome-elementor-errors-subsection" style="display: none;">
		<h2>Errors</h2>
		</div>
	</div>
		<?php
	}

	private function render_kit_token_field() {
		$opts = Options::get_options_with_defaults();
		$name = Options::option_name() . '[kit_token]';
		$kit_token = $opts['kit_token'] ?? '';
		printf(
			'<input type="text" class="regular-text" name="%s" value="%s" autocomplete="off" />',
			esc_attr( $name ),
			esc_attr( $kit_token )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Paste your Kit token here.', 'fontawesome-elementor-addon' )
		);
	}

	private function render_api_token_field() {
		$decrypted_api_token = Options::get_decrypted_api_token();
		$name = Options::option_name() . '[api_token]';
		$has_existing_api_token = ! \is_wp_error( $decrypted_api_token );
		$placeholder_message = $has_existing_api_token
			? '✅ ' . esc_html__( 'API token saved', 'fontawesome-elementor-addon' )
			: esc_html__( 'Paste an API token', 'fontawesome-elementor-addon' );
		printf(
			'<input type="password" class="regular-text" name="%s" placeholder="%s" autocomplete="off" />',
			esc_attr( $name ),
			esc_attr( $placeholder_message )
		);
		if ( $has_existing_api_token ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Paste a new API token to update it.', 'fontawesome-elementor-addon' )
			);
		}

		printf(
			'<p class="description">%1$s <a href="https://fontawesome.com/account/tokens" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s: Download Kits</p>',
			esc_html__( 'Make sure your', 'fontawesome-elementor-addon' ),
			esc_html__( 'API token', 'fontawesome-elementor-addon' ),
			esc_html__( 'has required scope', 'fontawesome-elementor-addon' )
		);
	}
}
