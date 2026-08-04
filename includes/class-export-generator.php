<?php
/**
 * Export Generator Class
 *
 * Generates scan reports in Markdown and JSON formats.
 * Ported from admin.js to support server-side generation.
 *
 * @package Lax_Abilities_Scout
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lax_Abilities_Scout_Export_Generator
{

    /**
     * Generate JSON export.
     *
     * @param array $data Scan data containing plugin_info and discovered.
     * @return string JSON string.
     */
    public function generate_json(array $data): string
    {
        $info = $data['plugin_info'];
        $discovered = $data['discovered'];

        $export_data = array(
            '$schema' => 'lax-abilities-scout/v1.2',
            'generator' => 'Lax Abilities Scout ' . (defined('LAX_ABILITIES_SCOUT_VERSION') ? LAX_ABILITIES_SCOUT_VERSION : '1.0.0'),
            'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'plugin' => array(
                'name' => $info['name'],
                'version' => $info['version'] ?? null,
                'author' => $info['author'] ?? null,
                'url' => $info['url'] ?? null,
            ),
            'scan_stats' => array(
                'files_scanned' => $discovered['stats']['files_scanned'],
                'files_errored' => $discovered['stats']['files_errored'],
                'total_files' => $discovered['stats']['total_files'],
                'truncated' => $discovered['stats']['truncated'],
                'total_hooks' => $discovered['stats']['total_hooks'],
                'total_routes' => $discovered['stats']['total_routes'],
                'total_shortcodes' => $discovered['stats']['total_shortcodes'],
                'potential_abilities_count' => $discovered['stats']['potential_abilities_count'],
                'scan_time_ms' => $discovered['stats']['scan_time_ms'],
            ),
            'primitives' => array_map(
                function ($a) {
                    return array(
                        'suggested_name' => $a['suggested_name'],
                        'label'          => $a['label'],
                        'ability_type'   => $a['ability_type'],
                        'role'           => $a['role'] ?? 'primitive',
                        'rest_adjacent'  => $a['rest_adjacent'] ?? false,
                        'confidence'     => $a['confidence'],
                        'score'          => $a['score'],
                        'source_type'    => $a['source_type'],
                        'source'         => $a['source'],
                    );
                },
                array_values( array_filter(
                    $discovered['potential_abilities'] ?? array(),
                    fn( $a ) => ( $a['role'] ?? 'primitive' ) === 'primitive'
                ) )
            ),
            'orchestrators' => array_map(
                function ($a) {
                    return array(
                        'suggested_name' => $a['suggested_name'],
                        'label'          => $a['label'],
                        'role'           => 'orchestrator',
                        'recommendation' => $a['recommendation'] ?? '',
                        'confidence'     => $a['confidence'],
                        'score'          => $a['score'],
                        'source_type'    => $a['source_type'],
                        'source'         => $a['source'],
                    );
                },
                array_values( array_filter(
                    $discovered['potential_abilities'] ?? array(),
                    fn( $a ) => ( $a['role'] ?? 'primitive' ) === 'orchestrator'
                ) )
            ),
            'raw_discoveries' => array(
                'actions' => $discovered['actions'],
                'filters' => $discovered['filters'],
                'rest_routes' => $discovered['rest_routes'],
                'shortcodes' => $discovered['shortcodes'],
            ),
        );

        return wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate Markdown export.
     *
     * @param array $data Scan data containing plugin_info and discovered.
     * @return string Markdown string.
     */
    public function generate_markdown(array $data): string
    {
        $info = $data['plugin_info'];
        $discovered = $data['discovered'];
        $abilities = $discovered['potential_abilities'] ?? array();
        $lines = array();

        // Title.
        $lines[] = '# Lax Abilities Scout Report: ' . $info['name'];
        $lines[] = '';

        // Metadata.
        $lines[] = '**Plugin:** ' . $info['name'] . (isset($info['version']) ? ' v' . $info['version'] : '');
        if (!empty($info['author'])) {
            $lines[] = '**Author:** ' . $info['author'];
        }
        if (!empty($info['url'])) {
            $lines[] = '**URL:** ' . $info['url'];
        }
        $lines[] = '**Scanned:** ' . gmdate('Y-m-d');
        $lines[] = '**Generator:** Lax Abilities Scout ' . (defined('LAX_ABILITIES_SCOUT_VERSION') ? LAX_ABILITIES_SCOUT_VERSION : '1.0.0');
        $lines[] = '';

        // AI Agent Preamble.
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## How to Use This Document';
        $lines[] = '';
        $lines[] = 'This document contains scan results from **Lax Abilities Scout**, which analyzed the ' .
            $info['name'] . ' plugin to discover hooks, REST routes, and shortcodes that could be ' .
            'registered as **abilities** using the WordPress Abilities API.';
        $lines[] = '';
        $lines[] = '### What is the Abilities API?';
        $lines[] = '';
        $lines[] = 'The WordPress Abilities API (WP 6.9+) provides a standardized way to register ' .
            'AI-callable units of functionality. Each ability has a unique name, description, ' .
            'JSON Schema input/output definitions, and an execute callback.';
        $lines[] = '';
        $lines[] = '### Registration Pattern';
        $lines[] = '';
        $lines[] = '```php';
        $lines[] = "// Step 1: Register a category (once per plugin, in wp_abilities_api_categories_init).";
        $lines[] = "add_action( 'wp_abilities_api_categories_init', function() {";
        $lines[] = "    wp_register_ability_category( 'namespace', array(";
        $lines[] = "        'label'       => __( 'My Plugin', 'text-domain' ),";
        $lines[] = "        'description' => __( 'Abilities provided by My Plugin.', 'text-domain' ),";
        $lines[] = "    ) );";
        $lines[] = "} );";
        $lines[] = '';
        $lines[] = "// Step 2: Register each ability in wp_abilities_api_init.";
        $lines[] = "add_action( 'wp_abilities_api_init', function() {";
        $lines[] = "    wp_register_ability( 'namespace/ability-name', array(";
        $lines[] = "        'label'       => __( 'Human-Readable Label', 'text-domain' ),";
        $lines[] = "        'description' => __( 'What this ability does, for AI agents.', 'text-domain' ),";
        $lines[] = "        'category'    => 'namespace', // required — must match a registered category slug";
        $lines[] = "        'input_schema' => array(";
        $lines[] = "            'type'       => 'object',";
        $lines[] = "            'properties' => array(";
        $lines[] = "                'param_name' => array(";
        $lines[] = "                    'type'        => 'string',";
        $lines[] = "                    'description' => 'Parameter description',";
        $lines[] = "                ),";
        $lines[] = "            ),";
        $lines[] = "            'required'             => array( 'param_name' ),";
        $lines[] = "            'additionalProperties' => false,";
        $lines[] = "        ),";
        $lines[] = "        'output_schema' => array(";
        $lines[] = "            'type'       => 'object',";
        $lines[] = "            'properties' => array(";
        $lines[] = "                'result' => array(";
        $lines[] = "                    'type'        => 'string',";
        $lines[] = "                    'description' => 'Result description',";
        $lines[] = "                ),";
        $lines[] = "            ),";
        $lines[] = "        ),";
        $lines[] = "        'execute_callback'    => static function( array \$input ) {";
        $lines[] = "            // Implement logic here. Return data matching output_schema or WP_Error.";
        $lines[] = "        },";
        $lines[] = "        'permission_callback' => function() {";
        $lines[] = "            return current_user_can( 'manage_options' );";
        $lines[] = "        },";
        $lines[] = "        'meta' => array(";
        $lines[] = "            'show_in_rest' => true, // exposes the ability via REST API and MCP";
        $lines[] = "        ),";
        $lines[] = "    ) );";
        $lines[] = "} );";
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '**Required:** `label`, `description`, `category`, `input_schema`, `output_schema`, `execute_callback`';
        $lines[] = '';
        $lines[] = '**Optional:** `permission_callback` (defaults to public), `meta` — use `show_in_rest: true` to expose via REST/MCP';
        $lines[] = '';
        $lines[] = '**Ability Name Pattern:** `namespace/ability-name` (lowercase alphanumeric + hyphens, exactly one forward slash)';
        $lines[] = '';
        $lines[] = '**Ability Types (internal classification):**';
        $lines[] = '- **tool** — Performs an action (create, update, delete, send, etc.)';
        $lines[] = '- **resource** — Returns data (get, list, check, query, etc.)';
        $lines[] = '';
        $lines[] = '### Abilities as Primitives';
        $lines[] = '';
        $lines[] = '**Key architectural principle:** Abilities are primitives — atomic, reusable units. REST endpoints are orchestrators that should *consume* and *chain* abilities, not become them.';
        $lines[] = '';
        $lines[] = '- **Register the hooks and shortcodes** (listed as "Primitive Abilities" below) as `wp_register_ability()` entries';
        $lines[] = '- **Update the REST endpoints** (listed as "REST Orchestrators" below) to call those abilities via the Abilities API';
        $lines[] = '- This makes your plugin composable: AI agents and other plugins can invoke individual abilities directly';
        $lines[] = '';

        // Scan Summary.
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Scan Summary';
        $lines[] = '';
        $lines[] = '| Metric | Value |';
        $lines[] = '|--------|-------|';
        $lines[] = '| Files Scanned | ' . $discovered['stats']['files_scanned'] . ' |';
        $lines[] = '| Total Hooks | ' . $discovered['stats']['total_hooks'] . ' |';
        $lines[] = '| REST Routes | ' . $discovered['stats']['total_routes'] . ' |';
        $lines[] = '| Shortcodes | ' . $discovered['stats']['total_shortcodes'] . ' |';
        $lines[] = '| Potential Abilities | ' . $discovered['stats']['potential_abilities_count'] . ' |';
        $lines[] = '| Scan Time | ' . $discovered['stats']['scan_time_ms'] . 'ms |';

        if (!empty($discovered['stats']['truncated'])) {
            $lines[] = '| **Note** | Scan truncated: ' . $discovered['stats']['files_scanned'] .
                ' of ' . $discovered['stats']['total_files'] . ' files |';
        }
        $lines[] = '';

        // Primitive Abilities and REST Orchestrators — separate sections.
        $primitives    = array_values( array_filter( $abilities, fn( $a ) => ( $a['role'] ?? 'primitive' ) === 'primitive' ) );
        $orchestrators = array_values( array_filter( $abilities, fn( $a ) => ( $a['role'] ?? 'primitive' ) === 'orchestrator' ) );

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Primitive Abilities';
        $lines[] = '';
        $lines[] = '> Register these hooks and shortcodes as `wp_register_ability()` entries — they are atomic, reusable units.';
        $lines[] = '';

        if ( empty( $primitives ) ) {
            $lines[] = 'No primitive abilities were discovered in this plugin.';
            $lines[] = '';
        } else {
            $prim_groups = array( 'high' => array(), 'medium' => array(), 'low' => array() );
            foreach ( $primitives as $a ) {
                $conf = $a['confidence'];
                $prim_groups[ isset( $prim_groups[ $conf ] ) ? $conf : 'low' ][] = $a;
            }

            foreach ( array( 'high', 'medium', 'low' ) as $level ) {
                if ( empty( $prim_groups[ $level ] ) ) {
                    continue;
                }
                $lines[] = '### ' . ucfirst( $level ) . ' Confidence (' . count( $prim_groups[ $level ] ) . ')';
                $lines[] = '';
                foreach ( $prim_groups[ $level ] as $ability ) {
                    $lines[] = '#### ' . $ability['label'];
                    $lines[] = '';
                    $lines[] = '- **Suggested Name:** `' . $ability['suggested_name'] . '`';
                    $lines[] = '- **Type:** ' . $ability['ability_type'];
                    $lines[] = '- **Role:** primitive';
                    if ( ! empty( $ability['rest_adjacent'] ) ) {
                        $lines[] = '- **REST Adjacent:** yes — this hook is in the same file as a REST route registration';
                    }
                    $lines[] = '- **Confidence:** ' . $ability['confidence'] . ' (score: ' . $ability['score'] . ')';
                    $lines[] = '- **Source Type:** ' . str_replace( '_', ' ', $ability['source_type'] );
                    if ( 'shortcode' === $ability['source_type'] ) {
                        $lines[] = '- **Shortcode:** `[' . $ability['source']['tag'] . ']`';
                    } else {
                        $lines[] = '- **Hook:** `' . $ability['source']['hook_name'] . '`';
                        $lines[] = '- **Parameters:** ' . ( $ability['source']['param_count'] ?? 0 );
                        if ( ! empty( $ability['source']['dynamic'] ) ) {
                            $lines[] = '- **Dynamic Hook:** yes (name constructed at runtime)';
                        }
                    }
                    $lines[] = '- **File:** `' . $ability['source']['file'] . ':' . $ability['source']['line'] . '`';
                    $lines[] = '';
                }
            }
        }

        if ( ! empty( $orchestrators ) ) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = '## REST Orchestrators';
            $lines[] = '';
            $lines[] = '> These REST endpoints are **orchestration layers**. They should *call* the primitive abilities above rather than become abilities themselves.';
            $lines[] = '';

            $orch_groups = array( 'high' => array(), 'medium' => array(), 'low' => array() );
            foreach ( $orchestrators as $a ) {
                $conf = $a['confidence'];
                $orch_groups[ isset( $orch_groups[ $conf ] ) ? $conf : 'low' ][] = $a;
            }

            foreach ( array( 'high', 'medium', 'low' ) as $level ) {
                if ( empty( $orch_groups[ $level ] ) ) {
                    continue;
                }
                $lines[] = '### ' . ucfirst( $level ) . ' (' . count( $orch_groups[ $level ] ) . ')';
                $lines[] = '';
                foreach ( $orch_groups[ $level ] as $ability ) {
                    $lines[] = '#### ' . $ability['label'];
                    $lines[] = '';
                    $lines[] = '- **Role:** orchestrator';
                    $lines[] = '- **REST Route:** `' . $ability['source']['full_route'] . '`';
                    $lines[] = '- **Namespace:** `' . $ability['source']['namespace'] . '`';
                    $lines[] = '- **Route Pattern:** `' . $ability['source']['route'] . '`';
                    $lines[] = '- **File:** `' . $ability['source']['file'] . ':' . $ability['source']['line'] . '`';
                    $lines[] = '';
                }
            }
        }

        // Raw Discoveries.
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Raw Discoveries';
        $lines[] = '';

        // Actions.
        if (!empty($discovered['actions'])) {
            $lines[] = '### Actions (' . count($discovered['actions']) . ')';
            $lines[] = '';
            $lines[] = '| Hook Name | File | Line | Params | Dynamic |';
            $lines[] = '|-----------|------|------|--------|---------|';
            foreach ($discovered['actions'] as $h) {
                $lines[] = '| `' . $h['hook_name'] . '` | ' . $h['file'] . ' | ' .
                    $h['line'] . ' | ' . $h['param_count'] . ' | ' . (!empty($h['dynamic']) ? 'yes' : 'no') . ' |';
            }
            $lines[] = '';
        }

        // Filters.
        if (!empty($discovered['filters'])) {
            $lines[] = '### Filters (' . count($discovered['filters']) . ')';
            $lines[] = '';
            $lines[] = '| Hook Name | File | Line | Params | Dynamic |';
            $lines[] = '|-----------|------|------|--------|---------|';
            foreach ($discovered['filters'] as $h) {
                $lines[] = '| `' . $h['hook_name'] . '` | ' . $h['file'] . ' | ' .
                    $h['line'] . ' | ' . $h['param_count'] . ' | ' . (!empty($h['dynamic']) ? 'yes' : 'no') . ' |';
            }
            $lines[] = '';
        }

        // REST Routes.
        if (!empty($discovered['rest_routes'])) {
            $lines[] = '### REST Routes (' . count($discovered['rest_routes']) . ')';
            $lines[] = '';
            $lines[] = '| Route | Namespace | File | Line |';
            $lines[] = '|-------|-----------|------|------|';
            foreach ($discovered['rest_routes'] as $r) {
                $lines[] = '| `' . $r['full_route'] . '` | ' . $r['namespace'] . ' | ' .
                    $r['file'] . ' | ' . $r['line'] . ' |';
            }
            $lines[] = '';
        }

        // Shortcodes.
        if (!empty($discovered['shortcodes'])) {
            $lines[] = '### Shortcodes (' . count($discovered['shortcodes']) . ')';
            $lines[] = '';
            $lines[] = '| Shortcode | File | Line |';
            $lines[] = '|-----------|------|------|';
            foreach ($discovered['shortcodes'] as $s) {
                $lines[] = '| `[' . $s['tag'] . ']` | ' . $s['file'] . ' | ' . $s['line'] . ' |';
            }
            $lines[] = '';
        }

        // Footer.
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '*Generated by [Lax Abilities Scout](https://github.com/laxmariappan/abilities-scout)*';

        return implode("\n", $lines);
    }
}
