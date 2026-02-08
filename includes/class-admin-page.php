<?php
/**
 * Admin Page Class
 *
 * Handles admin menu registration, page rendering, asset enqueuing,
 * and AJAX scanning requests.
 *
 * @package Abilities_Scout
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities_Scout_Admin_Page {

	/**
	 * Plugins to exclude from the scanner dropdown.
	 *
	 * @var array<string>
	 */
	private const EXCLUDED_PLUGINS = array(
		'abilities-api/abilities-api.php',
		'abilitiesexplorer/abilitiesexplorer.php',
		'abilities-scout/abilities-scout.php',
	);

	/**
	 * Initialize admin functionality.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_abilities_scout_scan', array( $this, 'ajax_scan_plugin' ) );
	}

	/**
	 * Add Scout submenu under the Abilities Explorer menu or standalone.
	 */
	public function add_submenu(): void {
		global $menu;

		$parent_slug    = 'abilitiesexplorer';
		$explorer_exists = false;

		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && $item[2] === $parent_slug ) {
					$explorer_exists = true;
					break;
				}
			}
		}

		if ( ! $explorer_exists ) {
			add_menu_page(
				__( 'Abilities', 'abilities-scout' ),
				__( 'Abilities', 'abilities-scout' ),
				'manage_options',
				'abilities-scout',
				array( $this, 'render_page' ),
				'dashicons-superhero',
				30
			);
			$parent_slug = 'abilities-scout';
		}

		add_submenu_page(
			$parent_slug,
			__( 'Scout', 'abilities-scout' ),
			__( 'Scout', 'abilities-scout' ),
			'manage_options',
			'abilities-scout',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets on the Scout page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		$allowed_hooks = array(
			'abilities_page_abilities-scout',
			'toplevel_page_abilities-scout',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'abilities-scout-admin',
			ABILITIES_SCOUT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ABILITIES_SCOUT_VERSION
		);

		wp_enqueue_script(
			'abilities-scout-admin',
			ABILITIES_SCOUT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ABILITIES_SCOUT_VERSION,
			true
		);

		wp_localize_script(
			'abilities-scout-admin',
			'abilitiesScout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'abilities_scout_scan' ),
				'strings' => array(
					'scanning'          => __( 'Scanning plugin...', 'abilities-scout' ),
					'noResults'         => __( 'No potential abilities discovered in this plugin.', 'abilities-scout' ),
					'error'             => __( 'An error occurred during scanning.', 'abilities-scout' ),
					'selectPlugin'      => __( 'Please select a plugin to scan.', 'abilities-scout' ),
					'potentialAbilities' => __( 'Potential Abilities', 'abilities-scout' ),
					'allDiscoveries'    => __( 'All Discoveries', 'abilities-scout' ),
					'noPotential'       => __( 'No strong ability candidates found. Check the raw discoveries below for hooks that might be useful.', 'abilities-scout' ),
					'tool'              => __( 'tool', 'abilities-scout' ),
					'resource'          => __( 'resource', 'abilities-scout' ),
				),
			)
		);
	}

	/**
	 * Render the Scout admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'abilities-scout' ) );
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		// Build plugin lists.
		$active_list   = array();
		$inactive_list = array();

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			// Skip excluded plugins.
			if ( in_array( $plugin_file, self::EXCLUDED_PLUGINS, true ) ) {
				continue;
			}

			$entry = array(
				'file'    => $plugin_file,
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
			);

			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$active_list[] = $entry;
			} else {
				$inactive_list[] = $entry;
			}
		}

		// Sort alphabetically by name.
		usort( $active_list, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
		usort( $inactive_list, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

		?>
		<div class="wrap abilities-scout-wrap">
			<h1><?php esc_html_e( 'Abilities Scout', 'abilities-scout' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Select a plugin to scan for potential abilities that could be registered with the WordPress Abilities API.', 'abilities-scout' ); ?>
			</p>

			<div class="abilities-scout-selector">
				<select id="abilities-scout-plugin-select">
					<option value=""><?php esc_html_e( '-- Select a Plugin --', 'abilities-scout' ); ?></option>

					<?php if ( ! empty( $active_list ) ) : ?>
						<optgroup label="<?php esc_attr_e( 'Active Plugins', 'abilities-scout' ); ?>">
							<?php foreach ( $active_list as $plugin ) : ?>
								<option value="<?php echo esc_attr( $plugin['file'] ); ?>">
									<?php
									echo esc_html( $plugin['name'] );
									if ( $plugin['version'] ) {
										echo ' (v' . esc_html( $plugin['version'] ) . ')';
									}
									?>
								</option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>

					<?php if ( ! empty( $inactive_list ) ) : ?>
						<optgroup label="<?php esc_attr_e( 'Inactive Plugins', 'abilities-scout' ); ?>">
							<?php foreach ( $inactive_list as $plugin ) : ?>
								<option value="<?php echo esc_attr( $plugin['file'] ); ?>">
									<?php
									echo esc_html( $plugin['name'] );
									if ( $plugin['version'] ) {
										echo ' (v' . esc_html( $plugin['version'] ) . ')';
									}
									?>
								</option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>
				</select>

				<button type="button" id="abilities-scout-scan-btn" class="button button-primary">
					<?php esc_html_e( 'Scout Abilities', 'abilities-scout' ); ?>
				</button>
			</div>

			<div id="abilities-scout-loading" class="abilities-scout-loading" style="display: none;">
				<span class="spinner is-active" style="float: none;"></span>
				<p><?php esc_html_e( 'Scanning plugin files...', 'abilities-scout' ); ?></p>
			</div>

			<div id="abilities-scout-results" style="display: none;"></div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for scanning a plugin.
	 */
	public function ajax_scan_plugin(): void {
		check_ajax_referer( 'abilities_scout_scan', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'abilities-scout' ) ) );
		}

		$plugin_file = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';

		if ( empty( $plugin_file ) ) {
			wp_send_json_error( array( 'message' => __( 'No plugin specified.', 'abilities-scout' ) ) );
		}

		// Validate plugin exists.
		$all_plugins = get_plugins();
		if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Plugin not found.', 'abilities-scout' ) ) );
		}

		$plugin_data = $all_plugins[ $plugin_file ];
		$plugin_dir  = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );

		// Verify path is within plugin directory.
		$real_plugin_dir = realpath( $plugin_dir );
		$real_wp_plugins = realpath( WP_PLUGIN_DIR );

		if ( false === $real_plugin_dir || false === $real_wp_plugins || ! str_starts_with( $real_plugin_dir, $real_wp_plugins ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin path.', 'abilities-scout' ) ) );
		}

		// Run scanner.
		$scanner = new Abilities_Scout_Scanner();
		$results = $scanner->scan_plugin( $real_plugin_dir );

		wp_send_json_success(
			array(
				'plugin_info' => array(
					'name'    => $plugin_data['Name'],
					'version' => $plugin_data['Version'],
					'author'  => wp_strip_all_tags( $plugin_data['Author'] ),
					'url'     => esc_url( $plugin_data['PluginURI'] ),
				),
				'discovered'  => $results,
			)
		);
	}
}
