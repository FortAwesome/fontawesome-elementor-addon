<?php

namespace FontAwesomeElementorAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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

  private function __construct() { /* noop */ }

  public function init() {
	  add_action('admin_menu', fn () => $this->add_menu() );
	  add_action('admin_init', fn () => $this->register_settings() );
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
      ['\FontAwesomeElementorAddon\Options', 'sanitize']
    );

    add_settings_section(
      $general_section_name,
      'General',
      function () {
        echo '<p>Configure the plugin.</p>';
      },
      self::PAGE_SLUG
    );

    add_settings_field(
      'api_token',
      'API Token',
      fn () => $this->render_api_token_field(),
      self::PAGE_SLUG,
      $general_section_name
    );

    add_settings_field(
      'kit_token',
      'Kit Token',
      fn () => $this->render_kit_token_field(),
      self::PAGE_SLUG,
      $general_section_name
    );

    add_settings_field(
      'load',
      'Load',
      fn () => $this->render_load_field(),
      self::PAGE_SLUG,
      $general_section_name
    );
  }

  private function render_page() {
    if ( ! current_user_can('manage_options') ) return;

    echo '<div class="wrap">';
    echo '<h1>Font Awesome Elementor Addon Settings</h1>';
    echo '<form method="post" action="options.php">';
      settings_fields(self::SETTINGS_GROUP);
      do_settings_sections(self::PAGE_SLUG);
      submit_button();
    echo '</form>';

    $this->render_kit_setup_section();

    echo '</div>';
  }

  private function render_kit_setup_section() {
    ?>
    <h2>Kit Setup</h2>
    <div class="fontawesome-elementor-addon-kit-setup">
      <button type="button" class="button button-secondary" id="fontawesome-elementor-addon-kit-setup-start">
        Setup Kit
      </button>

      <span class="spinner" id="fontawesome-elementor-addon-kit-setup-spinner" style="float:none;"></span>

      <span id="fontawesome-elementor-addon-kit-setup-status" style="margin-left:8px;"></span>
    </div>
    <?php
  }

  private function render_kit_token_field() {
    $opts = Options::get_options_with_defaults();
    $name = Options::option_name() . '[kit_token]';
    $kit_token = $opts['kit_token'] ?? '';
    printf(
      '<input type="text" class="regular-text" name="%s" value="%s" autocomplete="off" />',
      esc_attr($name),
      esc_attr($kit_token)
    );
    echo '<p class="description">Paste your Kit token here.</p>';
  }

  private function render_api_token_field() {
  	$decrypted_api_token = Options::get_decrypted_api_token();
    $name = Options::option_name() . '[api_token]';
    $api_token = is_wp_error( $decrypted_api_token ) ? '' : $decrypted_api_token;
    printf(
      '<input type="password" class="regular-text" name="%s" value="%s" autocomplete="off" />',
      esc_attr($name),
      esc_attr($api_token)
    );
    echo '<p class="description">Paste your Font Awesome API token here.</p>';
  }

  private function render_load_field() {
    $opts = Options::get_options_with_defaults();
    $name = Options::option_name() . '[load]';
    printf(
      '<label><input type="checkbox" name="%s" value="1" %s /> Enable loading</label>',
      esc_attr($name),
      checked(1, (int) $opts['load'], false)
    );
    echo '<p class="description">If enabled, the plugin will load Webfont + CSS asset on front end pages.</p>';
  }
}
