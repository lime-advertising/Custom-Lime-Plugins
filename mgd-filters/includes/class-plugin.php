<?php
/**
 * Core plugin orchestrator.
 *
 * @package MGD_Filters
 */

namespace MGD_Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Settings manager instance.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
		$this->settings = new Settings();
	}

	/**
	 * Register primary WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'admin_init', [ $this->settings, 'register_settings' ] );
		add_action( 'admin_menu', [ $this->settings, 'register_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this->settings, 'enqueue_assets' ] );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'mgd-filters', false, dirname( plugin_basename( MGD_FILTERS_FILE ) ) . '/languages' );
	}

	/**
	 * Activation handler.
	 *
	 * @return void
	 */
	public static function activate() {
		$settings = new Settings();

		if ( false === get_option( Settings::OPTION_KEY, false ) ) {
			add_option( Settings::OPTION_KEY, $settings->get_defaults() );
		}
	}

	/**
	 * Deactivation handler.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Intentionally empty for now; reserved for future cleanup.
	}
}
