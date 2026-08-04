<?php
/**
 * Draft Generator Class
 *
 * Generates PHP code stubs for registering abilities.
 *
 * @package Lax_Abilities_Scout
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lax_Abilities_Scout_Draft_Generator
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
            // Skip orchestrators — REST routes should consume abilities, not become them.
            if ('orchestrator' === ($ability['role'] ?? 'primitive')) {
                continue;
            }

            $stubs[] = array(
                'code'         => $this->generate_ability_stub($ability),
                'ability_name' => $ability['suggested_name'],
                'source_hook'  => $this->get_source_identifier($ability),
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
        $name      = $ability['suggested_name'];
        $label     = $ability['label'];
        $description = $this->generate_description($ability);
        $source    = $ability['source'];
        $source_id = $this->get_source_identifier($ability);
        $namespace = explode('/', $name)[0];
        $func_name = str_replace(array('-', '/'), '_', $name) . '_execute';
        $category_label = ucwords(str_replace('-', ' ', $namespace));

        $code = "<?php\n";
        $code .= "/**\n";
        $code .= " * Auto-generated ability stub by Lax Abilities Scout\n";
        $code .= " *\n";
        $code .= " * Source Hook: " . $source_id . "\n";
        $code .= " * File: " . $source['file'] . ":" . intval($source['line']) . "\n";
        $code .= " * Confidence: " . $ability['confidence'] . "\n";
        $code .= " *\n";
        $code .= " * TODO: Review and customize this ability before registering\n";
        $code .= " */\n\n";

        // Category registration — must fire before wp_abilities_api_init.
        $code .= "// Step 1: Register a category for this plugin's abilities (once, not per-ability).\n";
        $code .= "add_action( 'wp_abilities_api_categories_init', function() {\n";
        $code .= "\twp_register_ability_category( '" . addslashes($namespace) . "', array(\n";
        $code .= "\t\t'label'       => __('" . addslashes($category_label) . "', '" . addslashes($namespace) . "'),\n";
        $code .= "\t\t'description' => __( 'Abilities provided by " . addslashes($category_label) . ".', '" . addslashes($namespace) . "' ),\n";
        $code .= "\t) );\n";
        $code .= "} );\n\n";

        // Ability registration.
        $code .= "// Step 2: Register the ability.\n";
        $code .= "add_action( 'wp_abilities_api_init', function() {\n";
        $code .= "\twp_register_ability( '" . addslashes($name) . "', array(\n";
        $code .= "\t\t'label'       => '" . addslashes($label) . "',\n";
        $code .= "\t\t'description' => '" . addslashes($description) . "',\n";
        $code .= "\t\t'category'    => '" . addslashes($namespace) . "',\n\n";

        $code .= "\t\t'input_schema' => array(\n";
        $code .= "\t\t\t'type'       => 'object',\n";
        $code .= "\t\t\t'properties' => array(\n";
        $code .= "\t\t\t\t// TODO: Define input parameters based on " . $source_id . "\n";
        $code .= "\t\t\t),\n";
        $code .= "\t\t\t'required'   => array(),\n";
        $code .= "\t\t),\n\n";

        $code .= "\t\t'output_schema' => array(\n";
        $code .= "\t\t\t'type'       => 'object',\n";
        $code .= "\t\t\t'properties' => array(\n";
        $code .= "\t\t\t\t// TODO: Define the output structure\n";
        $code .= "\t\t\t),\n";
        $code .= "\t\t),\n\n";

        $code .= "\t\t'execute_callback'    => '" . addslashes($func_name) . "',\n\n";

        $code .= "\t\t'permission_callback' => function() {\n";
        $code .= "\t\t\treturn current_user_can( 'manage_options' );\n";
        $code .= "\t\t},\n\n";

        $code .= "\t\t'meta' => array(\n";
        $code .= "\t\t\t'show_in_rest' => true,\n";
        $code .= "\t\t),\n";
        $code .= "\t) );\n";
        $code .= "} );\n\n";

        $code .= "/**\n";
        $code .= " * Execute callback for " . addslashes($name) . "\n";
        $code .= " *\n";
        $code .= " * @param array \$input Input arguments matching input_schema.\n";
        $code .= " * @return array|WP_Error Output matching output_schema, or WP_Error on failure.\n";
        $code .= " */\n";
        $code .= "function " . $func_name . "( array \$input ) {\n";
        $code .= "\t// TODO: Implement ability logic using \$input.\n";
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
     * Generate a human-readable description derived from the hook name, route, or shortcode tag.
     *
     * @param array $ability Ability data.
     * @return string Description.
     */
    private function generate_description(array $ability): string
    {
        $type        = $ability['ability_type'];
        $source_type = $ability['source_type'];
        $source      = $ability['source'];

        if ('shortcode' === $source_type) {
            return sprintf(
                'Renders the [%s] shortcode and returns its formatted output.',
                $source['tag']
            );
        }

        if ('rest_route' === $source_type) {
            return 'tool' === $type
                ? sprintf( 'Performs actions at the %s REST endpoint.', $source['full_route'] )
                : sprintf( 'Retrieves data from the %s REST endpoint.', $source['full_route'] );
        }

        // Hook: strip plugin namespace prefix and humanize remaining words.
        $hook_name        = $source['hook_name'];
        $namespace        = explode( '/', $ability['suggested_name'] )[0];
        $namespace_prefix = str_replace( '-', '_', $namespace );

        if ( str_starts_with( $hook_name, $namespace_prefix . '_' ) ) {
            $human = substr( $hook_name, strlen( $namespace_prefix ) + 1 );
        } else {
            $human = $hook_name;
        }

        $human = str_replace( '_', ' ', $human );

        return 'tool' === $type
            ? ucfirst( $human ) . '.'
            : 'Returns ' . $human . '.';
    }
}
