<?php
/**
 * Plugin Name: Abilities Scout
 * Plugin URI: https://github.com/laxmariappan/abilities-scout
 * Description: Scans installed plugins and discovers potential abilities for the WordPress Abilities API. A companion to the Abilities Explorer plugin.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Lax Mariappan
 * Author URI: https://github.com/laxmariappan
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: abilities-scout
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-loading.
if ( defined( 'ABILITIES_SCOUT_VERSION' ) ) {
	return;
}

// Define plugin constants.
define( 'ABILITIES_SCOUT_VERSION', '1.1.0' );
define( 'ABILITIES_SCOUT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABILITIES_SCOUT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ABILITIES_SCOUT_PLUGIN_FILE', __FILE__ );

/**
 * Main Abilities Scout class.
 */
class Abilities_Scout {

	/**
	 * Single instance of the class.
	 *
	 * @var Abilities_Scout|null
	 */
	private static ?Abilities_Scout $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return Abilities_Scout
	 */
	public static function get_instance(): Abilities_Scout {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
		// Load plugin files.
		$this->load_dependencies();

		// Initialize admin.
		if ( is_admin() ) {
			$this->init_admin();
		}
	}

	/**
	 * Load plugin dependencies.
	 */
	private function load_dependencies(): void {
		require_once ABILITIES_SCOUT_PLUGIN_DIR . 'includes/class-scanner.php';
		require_once ABILITIES_SCOUT_PLUGIN_DIR . 'includes/class-mcp-tools.php';
		require_once ABILITIES_SCOUT_PLUGIN_DIR . 'includes/class-export-generator.php';
		require_once ABILITIES_SCOUT_PLUGIN_DIR . 'includes/class-draft-generator.php';

		// Initialize MCP tools (only if Abilities API is available).
		if ( function_exists( 'wp_register_ability' ) ) {
			new Abilities_Scout_MCP_Tools();
		}
	}

	/**
	 * Whether admin has been initialized.
	 */
	private bool $admin_initialized = false;

	/**
	 * Initialize admin functionality.
	 */
	private function init_admin(): void {
		if ( $this->admin_initialized ) {
			return;
		}
		$this->admin_initialized = true;

		require_once ABILITIES_SCOUT_PLUGIN_DIR . 'includes/class-admin-page.php';

		$admin_page = new Abilities_Scout_Admin_Page();
		$admin_page->init();
	}
}

// Initialize the plugin.
Abilities_Scout::get_instance();
