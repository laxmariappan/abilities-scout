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

        ob_start();

        echo "<?php\n";
        ?>
        /**
        * Auto-generated ability stub by Abilities Scout
        *
        * Source Hook:
        <?php echo esc_html($source_id); ?>

        * File:
        <?php echo esc_html($source['file']); ?>:
        <?php echo intval($source['line']); ?>

        * Confidence:
        <?php echo esc_html($ability['confidence']); ?>

        *
        * TODO: Review and customize this ability before registering
        */

        wp_register_ability( '
        <?php echo esc_html($name); ?>', array(
        'label' => '
        <?php echo esc_html($label); ?>',
        'description' => '
        <?php echo esc_html($description); ?>',

        'input_schema' => array(
        'type' => 'object',
        'properties' => array(
        // TODO: Define input parameters based on
        <?php echo esc_html($source_id); ?>

        // Analyze the hook signature to determine required parameters
        ),
        'required' => array(), // TODO: List required parameters
        ),

        'output_schema' => array(
        'type' => 'object',
        'properties' => array(
        // TODO: Define the output structure
        ),
        ),

        'execute_callback' => '
        <?php echo esc_html($func_name); ?>',

        'permission_callback' => function() {
        return current_user_can( 'manage_options' ); // TODO: Adjust permissions as needed
        },
        ) );

        /**
        * Execute callback for
        <?php echo esc_html($name); ?>

        *
        * @param array $args Input arguments matching input_schema
        * @return array Output matching output_schema
        */
        function
        <?php echo esc_html($func_name); ?>( $args ) {
        // TODO: Implement ability logic
        //
        // This ability should interact with:
        <?php echo esc_html($source_id); ?>

        //
        // Example implementation:
        // 1. Validate input parameters
        // 2. Call the source hook or function
        // 3. Process results
        // 4. Return formatted output

        return array(
        'success' => true,
        'data' => array(),
        // TODO: Define actual return structure
        );
        }
        <?php
        return ob_get_clean();
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
