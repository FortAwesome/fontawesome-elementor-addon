<?php

namespace FontAwesomeElementorAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use FontAwesomeElementorAddon\Setup_Kit;

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
			'Font Awesome Elementor Addon',
			function () {
				printf(
					'<p>%s</p>',
					esc_html__( 'Easily import your Font Awesome Kit into the Elementor Icon Library.', 'fontawesome-elementor-addon' )
				);
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

		$has_kit_been_set_up = Setup_Kit::has_kit_been_set_up($kit_token, $build_id);

		$this->render_kit_setup_section( [
			"is_configured" => $is_configured,
			"has_kit_been_set_up" => $has_kit_been_set_up,
			"last_kit_refresh_at" => $last_kit_refresh_at,
		] );

		echo '</div>';
	}

	private function render_kit_setup_section( $params = [] ) {
		$is_configured = is_array( $params ) ?  boolval ( $params["is_configured"] ?? false ) : false;
		$is_form_changed = is_array( $params ) ?  boolval ( $params["is_form_changed"] ?? false ) : false;
		$has_kit_been_set_up = is_array( $params ) ?  boolval ( $params["has_kit_been_set_up"] ?? false ) : false;
		$last_kit_refresh_at = is_array( $params ) ?  ( $params["last_kit_refresh_at"] ?? null ) : null;
		$last_kit_refresh_at_formatted = is_int( $last_kit_refresh_at ) ? Options::format_unix_timestamp( $last_kit_refresh_at ) : null;

		$button_label = ( $is_configured && $has_kit_been_set_up )
			? esc_html__( 'Refresh Setup', 'fontawesome-elementor-addon' )
			: esc_html__( 'Setup Kit', 'fontawesome-elementor-addon' );
		$button_disabled_attr = ! $is_configured ? 'disabled' : '';
		?>
	<h2><?= esc_html__('Kit Setup', 'fontawesome-elementor-addon') ?></h2>
	<div class="fontawesome-elementor-addon-kit-setup">
	    <div>
		<p>After saving any changes, click "Setup Kit" to automatically download and install the kit for self-hosting on your WordPress server.</p>
		<p>When it's done, you can expect to see the changes reflected in the Elementor Icon Library.</p>
		<button
		    type="button"
			class="button button-secondary"
			id="fontawesome-elementor-addon-kit-setup-start"
			<?= esc_html__( $button_disabled_attr, 'fontawesome-elementor-addon' ) ?>
		>
		Setup Kit
		</button>

		<span class="spinner" id="fontawesome-elementor-addon-kit-setup-spinner" style="float:none;"></span>

		<span id="fontawesome-elementor-addon-kit-setup-status" style="margin-left:8px;"></span>
		</div>
		<?php if ( $last_kit_refresh_at_formatted ) : ?>
		<div>
			Last refreshed at: <span id="fontawesome-elementor-addon-last-kit-refresh-at"><?= $last_kit_refresh_at_formatted ?></span>
		</div>
		<?php endif; ?>
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
		$api_token = is_wp_error( $decrypted_api_token ) ? '' : $decrypted_api_token;
		printf(
			'<input type="password" class="regular-text" name="%s" value="%s" autocomplete="off" />',
			esc_attr( $name ),
			esc_attr( $api_token )
		);
		printf(
			'<p class="description">%s</p><p class="description">%s: Download Kits</p>',
			esc_html__( 'Paste your Font Awesome API token here.', 'fontawesome-elementor-addon' ),
			esc_html__( 'Make sure it has the required scope', 'fontawesome-elementor-addon' )
		);
	}
}
