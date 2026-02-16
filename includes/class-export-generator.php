<?php
/**
 * Export Generator Class
 *
 * Generates scan reports in Markdown and JSON formats.
 * Ported from admin.js to support server-side generation.
 *
 * @package Abilities_Scout
 */

if (!defined('ABSPATH')) {
    exit;
}

class Abilities_Scout_Export_Generator
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
            '$schema' => 'abilities-scout/v1',
            'generator' => 'Abilities Scout ' . (defined('ABILITIES_SCOUT_VERSION') ? ABILITIES_SCOUT_VERSION : '1.0.0'),
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
            'potential_abilities' => array_map(
                function ($a) {
                    return array(
                        'suggested_name' => $a['suggested_name'],
                        'label' => $a['label'],
                        'ability_type' => $a['ability_type'],
                        'confidence' => $a['confidence'],
                        'score' => $a['score'],
                        'source_type' => $a['source_type'],
                        'source' => $a['source'],
                    );
                },
                $discovered['potential_abilities'] ?? array()
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
        $lines[] = '# Abilities Scout Report: ' . $info['name'];
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
        $lines[] = '**Generator:** Abilities Scout ' . (defined('ABILITIES_SCOUT_VERSION') ? ABILITIES_SCOUT_VERSION : '1.0.0');
        $lines[] = '';

        // AI Agent Preamble.
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## How to Use This Document';
        $lines[] = '';
        $lines[] = 'This document contains scan results from **Abilities Scout**, which analyzed the ' .
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
        $lines[] = "add_action( 'wp_abilities_api_init', function() {";
        $lines[] = "    wp_register_ability( 'namespace/ability-name', array(";
        $lines[] = "        'label'               => __( 'Human-Readable Label', 'text-domain' ),";
        $lines[] = "        'description'          => __( 'What this ability does, for AI agents.', 'text-domain' ),";
        $lines[] = "        'input_schema'         => array(";
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
        $lines[] = "        'output_schema'        => array(";
        $lines[] = "            'type'       => 'object',";
        $lines[] = "            'properties' => array(";
        $lines[] = "                'result' => array(";
        $lines[] = "                    'type'        => 'string',";
        $lines[] = "                    'description' => 'Result description',";
        $lines[] = "                ),";
        $lines[] = "            ),";
        $lines[] = "        ),";
        $lines[] = "        'execute_callback'     => 'my_execute_function',";
        $lines[] = "        'permission_callback'  => function() {";
        $lines[] = "            return current_user_can( 'manage_options' );";
        $lines[] = "        },";
        $lines[] = "    ) );";
        $lines[] = "} );";
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '**Required arguments:** `label`, `description`, `input_schema`, `output_schema`, `execute_callback`';
        $lines[] = '';
        $lines[] = '**Optional:** `permission_callback` (defaults to true), `meta` (arbitrary metadata array)';
        $lines[] = '';
        $lines[] = '**Ability Name Pattern:** `namespace/ability-name` (lowercase alphanumeric + hyphens, exactly one forward slash)';
        $lines[] = '';
        $lines[] = '**Ability Types:**';
        $lines[] = '- **tool** -- Performs an action (create, update, delete, send, etc.)';
        $lines[] = '- **resource** -- Returns data (get, list, check, query, etc.)';
        $lines[] = '';
        $lines[] = '### Your Task';
        $lines[] = '';
        $lines[] = 'Use the potential abilities listed below to generate `wp_register_ability()` code for the ' .
            $info['name'] . ' plugin. Each entry includes a suggested name, type, confidence score, and the source ' .
            'hook/route/shortcode it was derived from. High-confidence items are the strongest candidates.';
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

        // Potential Abilities.
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Potential Abilities';
        $lines[] = '';

        if (empty($abilities)) {
            $lines[] = 'No potential abilities were discovered in this plugin.';
            $lines[] = '';
        } else {
            $groups = array(
                'high' => array(),
                'medium' => array(),
                'low' => array(),
            );

            foreach ($abilities as $a) {
                $conf = $a['confidence'];
                if (isset($groups[$conf])) {
                    $groups[$conf][] = $a;
                } else {
                    $groups['low'][] = $a;
                }
            }

            foreach (array('high', 'medium', 'low') as $level) {
                if (empty($groups[$level])) {
                    continue;
                }

                $lines[] = '### ' . ucfirst($level) . ' Confidence (' . count($groups[$level]) . ')';
                $lines[] = '';

                foreach ($groups[$level] as $ability) {
                    $lines[] = '#### ' . $ability['label'];
                    $lines[] = '';
                    $lines[] = '- **Suggested Name:** `' . $ability['suggested_name'] . '`';
                    $lines[] = '- **Type:** ' . $ability['ability_type'];
                    $lines[] = '- **Confidence:** ' . $ability['confidence'] . ' (score: ' . $ability['score'] . ')';
                    $lines[] = '- **Source Type:** ' . str_replace('_', ' ', $ability['source_type']);

                    if ('rest_route' === $ability['source_type']) {
                        $lines[] = '- **REST Route:** `' . $ability['source']['full_route'] . '`';
                        $lines[] = '- **Namespace:** `' . $ability['source']['namespace'] . '`';
                        $lines[] = '- **Route Pattern:** `' . $ability['source']['route'] . '`';
                    } elseif ('shortcode' === $ability['source_type']) {
                        $lines[] = '- **Shortcode:** `[' . $ability['source']['tag'] . ']`';
                    } else {
                        $lines[] = '- **Hook:** `' . $ability['source']['hook_name'] . '`';
                        $lines[] = '- **Parameters:** ' . ($ability['source']['param_count'] ?? 0);
                        if (!empty($ability['source']['dynamic'])) {
                            $lines[] = '- **Dynamic Hook:** yes (name constructed at runtime)';
                        }
                    }

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
        $lines[] = '*Generated by [Abilities Scout](https://github.com/laxmariappan/abilities-scout)*';

        return implode("\n", $lines);
    }
}
