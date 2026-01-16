<?php

namespace FontAwesomeElementorAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use FontAwesomeLib\Crypto;

class Settings_Page {
  const PAGE_SLUG = 'fontawesome-elementor-addon-settings';
  const SETTINGS_GROUP = 'fontawesome_elementor_addon_settings_group';
  const BACKEND_ONLY_SETTINGS_TEXT = [ 'kit_assets_relative_dir', 'option_schema_version' ];

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

  public function __construct() { /* noop */ }

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
    $kit_setup_section_name = 'fontawesome_elementor_addon_kit_setup_section';

    register_setting(
      self::SETTINGS_GROUP,
      Options::options_key(),
      fn ( $input ) => $this->sanitize( $input )
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

    add_settings_section(
      $kit_setup_section_name,
      'Kit Setup',
      function () {
      ?>
        <div class="fontawesome-elementor-addon-kit-setup">
          <button type="button" class="button button-secondary" id="fontawesome-elementor-addon-kit-setup-start">
            Setup Kit
          </button>

          <span class="spinner" id="fontawesome-elementor-addon-kit-setup-spinner" style="float:none;"></span>

          <span id="fontawesome-elementor-addon-kit-setup-status" style="margin-left:8px;"></span>
        </div>
      <?php
      },
      self::PAGE_SLUG
    );
  }

  private function sanitize($input) {
	  // Existing saved settings (may include keys not on the form)
	  $existing = get_option(Options::options_key(), []);
	  if (!is_array($existing)) $existing = [];

		$output = $existing;

	  foreach( self::BACKEND_ONLY_SETTINGS_TEXT as $key ) {
		if ( array_key_exists( $key, $input ) ) {
	      $output[ $key ] = sanitize_text_field(wp_unslash($input[$key]));
	    }
	  }

    if (array_key_exists('kit_token', $input)) {
      $output['kit_token'] = sanitize_text_field(wp_unslash($input['kit_token']));
    }

    if (array_key_exists('api_token', $input)) {
     	if ( defined( 'LOGGED_IN_SALT' ) &&
			is_string( LOGGED_IN_SALT ) &&
			defined( 'LOGGED_IN_KEY' ) &&
			is_string( LOGGED_IN_KEY ) ) {
	      	$crypto = new Crypto(["key" => LOGGED_IN_KEY, "salt" => LOGGED_IN_SALT]);
			$sanitized = sanitize_text_field(wp_unslash($input['api_token']));
			$encrypted = $crypto->encrypt( $sanitized );
			if(! is_wp_error( $encrypted) ) {
    			$output['api_token'] = $encrypted;
			}
       }
    }

    if (array_key_exists('load', $input)) {
    	$output['load'] = ! empty($input['load']) ? 1 : 0;
    }

    return $output;
  }

  private function get_options() {
    $defaults = [
      'kit_token' => '',
      'api_token' => '',
      'load'  => 0,
    ];
    $saved = get_option(Options::options_key(), []);
    return wp_parse_args(is_array($saved) ? $saved : [], $defaults);
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
    echo '</div>';
  }

  private function render_kit_token_field() {
    $opts = $this->get_options();
    $name = Options::options_key() . '[kit_token]';
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
    $name = Options::options_key() . '[api_token]';
    $api_token = is_wp_error( $decrypted_api_token ) ? '' : $decrypted_api_token;
    printf(
      '<input type="password" class="regular-text" name="%s" value="%s" autocomplete="off" />',
      esc_attr($name),
      esc_attr($api_token)
    );
    echo '<p class="description">Paste your Font Awesome API token here.</p>';
  }

  private function render_load_field() {
    $opts = $this->get_options();
    $name = Options::options_key() . '[load]';
    printf(
      '<label><input type="checkbox" name="%s" value="1" %s /> Enable loading</label>',
      esc_attr($name),
      checked(1, (int) $opts['load'], false)
    );
    echo '<p class="description">If enabled, the plugin will load Webfont + CSS asset on front end pages.</p>';
  }
}
