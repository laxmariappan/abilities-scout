<?php
/**
 * Draft Generator Class
 *
 * Generates PHP code stubs for registering abilities.
 *
 * @package Abilities_Scout
 */

if (!defined('ABSPATH')) {
    exit;
}

class Abilities_Scout_Draft_Generator
{

    /**
     * Generate multiple stubs from scan results.
     *
     * @param array  $scan_results   Scan results.
     * @param string $min_confidence Minimum confidence level (high, medium, low).
     * @return array Array of stubs.
     */
    public function generate_multiple_stubs(array $scan_results, string $min_confidence = 'high'): array
    {
        $abilities = $scan_results['potential_abilities'] ?? array();
        $stubs = array();

        // Filter by confidence.
        $abilities = $this->filter_by_confidence($abilities, $min_confidence);

        foreach ($abilities as $ability) {
            $stubs[] = array(
                'code' => $this->generate_ability_stub($ability),
                'ability_name' => $ability['suggested_name'],
                'source_hook' => $this->get_source_identifier($ability),
            );
        }

        return $stubs;
    }

    /**
     * Generate a single ability stub.
     *
     * @param array $ability Ability data.
     * @return string PHP code.
     */
    public function generate_ability_stub(array $ability): string
    {
        $name = $ability['suggested_name'];
        $label = $ability['label'];
        $description = $this->generate_description($ability);
        $source = $ability['source'];
        $source_id = $this->get_source_identifier($ability);
        $func_name = str_replace(array('-', '/'), '_', $name) . '_execute';

        $code = "<?php\n";
        $code .= "/**\n";
        $code .= " * Auto-generated ability stub by Abilities Scout\n";
        $code .= " *\n";
        $code .= " * Source Hook: " . esc_html($source_id) . "\n";
        $code .= " * File: " . esc_html($source['file']) . ":" . intval($source['line']) . "\n";
        $code .= " * Confidence: " . esc_html($ability['confidence']) . "\n";
        $code .= " *\n";
        $code .= " * TODO: Review and customize this ability before registering\n";
        $code .= " */\n\n";

        $code .= "wp_register_ability( '" . esc_html($name) . "', array(\n";
        $code .= "\t'label'       => '" . esc_html($label) . "',\n";
        $code .= "\t'description' => '" . esc_html($description) . "',\n\n";

        $code .= "\t'input_schema' => array(\n";
        $code .= "\t\t'type'       => 'object',\n";
        $code .= "\t\t'properties' => array(\n";
        $code .= "\t\t\t// TODO: Define input parameters based on " . esc_html($source_id) . "\n";
        $code .= "\t\t),\n";
        $code .= "\t\t'required'   => array(),\n";
        $code .= "\t),\n\n";

        $code .= "\t'output_schema' => array(\n";
        $code .= "\t\t'type'       => 'object',\n";
        $code .= "\t\t'properties' => array(\n";
        $code .= "\t\t\t// TODO: Define the output structure\n";
        $code .= "\t\t),\n";
        $code .= "\t),\n\n";

        $code .= "\t'execute_callback'    => '" . esc_html($func_name) . "',\n\n";

        $code .= "\t'permission_callback' => function() {\n";
        $code .= "\t\treturn current_user_can( 'manage_options' );\n";
        $code .= "\t},\n";
        $code .= ") );\n\n";

        $code .= "/**\n";
        $code .= " * Execute callback for " . esc_html($name) . "\n";
        $code .= " *\n";
        $code .= " * @param array \$args Input arguments matching input_schema\n";
        $code .= " * @return array Output matching output_schema\n";
        $code .= " */\n";
        $code .= "function " . esc_html($func_name) . "( \$args ) {\n";
        $code .= "\t// TODO: Implement ability logic\n";
        $code .= "\treturn array(\n";
        $code .= "\t\t'success' => true,\n";
        $code .= "\t\t'data'    => array(),\n";
        $code .= "\t);\n";
        $code .= "}\n";

        return $code;
    }

    /**
     * Filter abilities by confidence.
     *
     * @param array  $abilities List of abilities.
     * @param string $threshold Threshold.
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

    /**
     * Get source identifier (hook name, route, or tag).
     *
     * @param array $ability Ability data.
     * @return string Identifier.
     */
    private function get_source_identifier(array $ability): string
    {
        $source = $ability['source'];
        $type = $ability['source_type'];

        if ('rest_route' === $type) {
            return $source['full_route'];
        } elseif ('shortcode' === $type) {
            return '[' . $source['tag'] . ']';
        } else {
            return $source['hook_name'];
        }
    }

    /**
     * Generate a basic description.
     *
     * @param array $ability Ability data.
     * @return string Description.
     */
    private function generate_description(array $ability): string
    {
        $type = $ability['ability_type'];
        $id = $this->get_source_identifier($ability);

        if ('tool' === $type) {
            return sprintf('Performs actions related to %s.', $id);
        }
        return sprintf('Retrieves data from %s.', $id);
    }
}
