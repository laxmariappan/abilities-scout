<?php
/**
 * MCP Tools Class
 *
 * Registers WordPress abilities for AI agents to interact with the scanner.
 *
 * @package Abilities_Scout
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Abilities_Scout_MCP_Tools
 */
class Abilities_Scout_MCP_Tools
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('wp_abilities_api_categories_init', array($this, 'register_ability_category'));

        add_action('wp_abilities_api_init', array($this, 'register_abilities'));
    }

    /**
     * Register abilities category
     */
    public function register_ability_category(): void
    {
        wp_register_ability_category(
            'abilities-scout',
            array(
                'label' => __('Abilities Scout', 'abilities-scout'),
                'description' => __('Scans WordPress plugins to detect registerable abilities from hooks, REST routes, and shortcodes, and exposes MCP tools for programmatic access to scan, export, and draft results.', 'abilities-scout'),
            )
        );
    }

    /**
     * Register abilities.
     */
    public function register_abilities(): void
    {

        // Tool: Scan a plugin.
        wp_register_ability(
            'abilities-scout/scan',
            array(
                'label' => __('Scan Plugin for Abilities', 'abilities-scout'),
                'description' => __('Scans a specific WordPress plugin to discover hooks, REST routes, and shortcodes that can be used as abilities.', 'abilities-scout'),
                'category' => 'abilities-scout',
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'plugin' => array(
                            'type' => 'string',
                            'description' => 'Plugin file path (e.g., akismet/akismet.php)',
                        ),
                        'confidence' => array(
                            'type' => 'string',
                            'enum' => array('high', 'medium', 'low'),
                            'default' => 'low',
                            'description' => 'Minimum confidence level to return',
                        ),
                    ),
                    'required' => array('plugin'),
                ),
                'output_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'plugin_info' => array('type' => 'object'),
                        'potential_abilities' => array('type' => 'array'),
                        'stats' => array('type' => 'object'),
                    ),
                ),
                'execute_callback' => array($this, 'execute_scan'),
                'permission_callback' => array($this, 'check_permissions'),
                'meta' => array(
                    'show_in_rest' => true,
                    'mcp' => array(
                        'public' => true,
                        'type' => 'tool',
                    ),
                ),
            )
        );

        // Tool: Export scan results.
        wp_register_ability(
            'abilities-scout/export',
            array(
                'label' => __('Export Scan Results', 'abilities-scout'),
                'description' => __('Generates a report of discovered abilities in Markdown or JSON format.', 'abilities-scout'),
                'category' => 'abilities-scout',
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'plugin' => array(
                            'type' => 'string',
                            'description' => 'Plugin file path (e.g., akismet/akismet.php)',
                        ),
                        'format' => array(
                            'type' => 'string',
                            'enum' => array('json', 'markdown'),
                            'default' => 'json',
                            'description' => 'Output format',
                        ),
                    ),
                    'required' => array('plugin'),
                ),
                'output_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'content' => array(
                            'type' => 'string',
                            'description' => 'Exported content',
                        ),
                        'format' => array(
                            'type' => 'string',
                        ),
                    ),
                ),
                'execute_callback' => array($this, 'execute_export'),
                'permission_callback' => array($this, 'check_permissions'),
                'meta' => array(
                    'show_in_rest' => true,
                    'mcp' => array(
                        'public' => true,
                        'type' => 'tool',
                    ),
                ),
            )
        );

        // Tool: Draft implementation code.
        wp_register_ability(
            'abilities-scout/draft',
            array(
                'label' => __('Draft Ability Code', 'abilities-scout'),
                'description' => __('Generates PHP code stubs for registering discovered abilities.', 'abilities-scout'),
                'category' => 'abilities-scout',
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'plugin' => array(
                            'type' => 'string',
                            'description' => 'Plugin file path',
                        ),
                        'confidence' => array(
                            'type' => 'string',
                            'enum' => array('high', 'medium', 'low'),
                            'default' => 'high',
                            'description' => 'Minimum confidence level to include',
                        ),
                    ),
                    'required' => array('plugin'),
                ),
                'output_schema' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'code' => array('type' => 'string'),
                            'ability_name' => array('type' => 'string'),
                            'source_hook' => array('type' => 'string'),
                        ),
                    ),
                ),
                'execute_callback' => array($this, 'execute_draft'),
                'permission_callback' => array($this, 'check_permissions'),
                'meta' => array(
                    'show_in_rest' => true,
                    'mcp' => array(
                        'public' => true,
                        'type' => 'tool',
                    ),
                ),
            )
        );
    }

    /**
     * Check permissions.
     *
     * @return bool
     */
    public function check_permissions(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Private helper to perform a full scan.
     *
     * @param string $plugin_file Plugin file path.
     * @return array|WP_Error Full scan results or error.
     */
    private function scan_plugin_full(string $plugin_file)
    {
        $validation = $this->validate_plugin($plugin_file);
        if (is_wp_error($validation)) {
            return $validation;
        }

        list($plugin_data, $plugin_dir) = $validation;

        // Run scanner.
        if (!class_exists('Abilities_Scout_Scanner')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-scanner.php';
        }

        $scanner = new Abilities_Scout_Scanner();
        $results = $scanner->scan_plugin($plugin_dir);

        return array(
            'plugin_info' => array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => wp_strip_all_tags($plugin_data['Author']),
                'url' => esc_url($plugin_data['PluginURI']),
            ),
            'results' => $results,
        );
    }

    /**
     * Execute scan ability.
     *
     * @param array $args Input arguments.
     * @return array|WP_Error Scan results or error.
     */
    public function execute_scan(array $args)
    {
        $plugin_file = $args['plugin'];
        $confidence_threshold = $args['confidence'] ?? 'low';

        $scan_data = $this->scan_plugin_full($plugin_file);
        if (is_wp_error($scan_data)) {
            return $scan_data;
        }

        $results = $scan_data['results'];

        // Filter by confidence if needed.
        if ('low' !== $confidence_threshold) {
            $results['potential_abilities'] = $this->filter_by_confidence(
                $results['potential_abilities'],
                $confidence_threshold
            );
        }

        return array(
            'plugin_info' => $scan_data['plugin_info'],
            'potential_abilities' => $results['potential_abilities'],
            'stats' => $results['stats'],
        );
    }

    /**
     * Execute export ability.
     *
     * @param array $args Input arguments.
     * @return array|WP_Error Export results or error.
     */
    public function execute_export(array $args)
    {
        $plugin_file = $args['plugin'];
        $format = $args['format'] ?? 'json';

        $scan_data = $this->scan_plugin_full($plugin_file);
        if (is_wp_error($scan_data)) {
            return $scan_data;
        }

        $export_data = array(
            'plugin_info' => $scan_data['plugin_info'],
            'discovered' => $scan_data['results'],
        );

        $generator = new Abilities_Scout_Export_Generator();

        if ('markdown' === $format) {
            $content = $generator->generate_markdown($export_data);
        } else {
            $content = $generator->generate_json($export_data);
        }

        return array(
            'content' => $content,
            'format' => $format,
        );
    }

    /**
     * Execute draft ability.
     *
     * @param array $args Input arguments.
     * @return array|WP_Error Draft results or error.
     */
    public function execute_draft(array $args)
    {
        $plugin_file = $args['plugin'];
        $confidence = $args['confidence'] ?? 'high';

        // Get scan results.
        $scan_results = $this->execute_scan(
            array(
                'plugin' => $plugin_file,
                'confidence' => $confidence,
            )
        );

        if (is_wp_error($scan_results)) {
            return $scan_results;
        }

        $generator = new Abilities_Scout_Draft_Generator();

        return $generator->generate_multiple_stubs(
            $scan_results,
            $confidence
        );
    }

    /**
     * Validate plugin path and existence.
     *
     * @param string $plugin_file Relative plugin path.
     * @return array|WP_Error Array [plugin_data, plugin_dir] or error.
     */
    private function validate_plugin(string $plugin_file)
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        if (!isset($all_plugins[$plugin_file])) {
            return new WP_Error('plugin_not_found', __('Plugin not found.', 'abilities-scout'));
        }

        $plugin_data = $all_plugins[$plugin_file];
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_file);

        // Security check.
        $real_plugin_dir = realpath($plugin_dir);
        $real_wp_plugins = realpath(WP_PLUGIN_DIR);

        if (false === $real_plugin_dir || false === $real_wp_plugins || !str_starts_with($real_plugin_dir, $real_wp_plugins)) {
            return new WP_Error('invalid_path', __('Invalid plugin path.', 'abilities-scout'));
        }

        return array($plugin_data, $real_plugin_dir);
    }

    /**
     * Filter potential abilities by confidence.
     *
     * @param array  $abilities List of abilities.
     * @param string $threshold Threshold (high, medium, low).
     * @return array Filtered abilities.
     */
    private function filter_by_confidence(array $abilities, string $threshold): array
    {
        if ('low' === $threshold) {
            return $abilities;
        }

        $levels = array(
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        );

        $min_level = $levels[$threshold] ?? 1;

        return array_values(
            array_filter(
                $abilities,
                function ($ability) use ($levels, $min_level) {
                    $conf = $ability['confidence'] ?? 'low';
                    $level = $levels[$conf] ?? 1;
                    return $level >= $min_level;
                }
            )
        );
    }
}
