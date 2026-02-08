<?php
/**
 * Scanner Class
 *
 * Static code analysis engine that scans plugin PHP files
 * to discover hooks, REST routes, and shortcodes using token_get_all().
 *
 * @package Abilities_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities_Scout_Scanner {

	/**
	 * Maximum number of files to scan per plugin.
	 */
	private const MAX_FILES = 500;

	/**
	 * Maximum file size in bytes (2 MB).
	 */
	private const MAX_FILE_SIZE = 2 * 1024 * 1024;

	/**
	 * Verbs that indicate a "tool" ability (performs an action).
	 *
	 * @var array<string>
	 */
	private const TOOL_VERBS = array(
		'submit', 'send', 'create', 'delete', 'update', 'process',
		'add', 'remove', 'save', 'run', 'export', 'import',
		'activate', 'deactivate', 'clear', 'flush', 'purge',
		'publish', 'unpublish', 'approve', 'reject', 'reset',
		'schedule', 'unschedule', 'pause', 'resume', 'trigger',
		'register', 'unregister', 'enable', 'disable', 'toggle',
		'insert', 'set', 'put', 'post', 'patch', 'write',
	);

	/**
	 * Verbs that indicate a "resource" ability (returns data).
	 *
	 * @var array<string>
	 */
	private const RESOURCE_VERBS = array(
		'get', 'list', 'check', 'query', 'count', 'search',
		'fetch', 'read', 'load', 'retrieve', 'find', 'lookup',
		'verify', 'validate', 'is', 'has', 'can',
	);

	/**
	 * Suffixes that indicate infrastructure/UI plumbing (not abilities).
	 *
	 * @var array<string>
	 */
	private const INFRASTRUCTURE_SUFFIXES = array(
		'_nonce', '_sanitize', '_enqueue', '_css', '_js',
		'_style', '_script', '_styles', '_scripts',
		'_column', '_columns', '_row', '_rows',
		'_menu', '_submenu', '_notice', '_notices',
		'_message', '_messages', '_class', '_classes',
		'_attr', '_attrs', '_attribute', '_attributes',
		'_template', '_widget', '_widgets',
		'_metabox', '_meta_box', '_meta_boxes',
		'_capability', '_capabilities', '_screen',
		'_tab', '_tabs', '_section', '_sections',
		'_field', '_fields', '_option_page',
		'_display', '_render', '_output', '_html',
		'_markup', '_view', '_form', '_input',
		'_label', '_title', '_heading', '_header',
		'_footer', '_sidebar', '_nav', '_breadcrumb',
		'_link', '_url', '_path', '_icon', '_image',
	);

	/**
	 * Directories to exclude from scanning.
	 *
	 * @var array<string>
	 */
	private const EXCLUDED_DIRS = array(
		'vendor',
		'node_modules',
		'tests',
		'test',
		'assets',
		'languages',
		'lib',
		'libs',
		'.git',
		'.github',
	);

	/**
	 * WordPress core hooks to filter out (not useful as abilities).
	 *
	 * @var array<string>
	 */
	private const CORE_HOOKS_BLOCKLIST = array(
		'init',
		'wp_init',
		'admin_init',
		'plugins_loaded',
		'after_setup_theme',
		'wp_loaded',
		'wp_head',
		'wp_footer',
		'wp_enqueue_scripts',
		'admin_enqueue_scripts',
		'admin_menu',
		'admin_bar_menu',
		'admin_notices',
		'admin_head',
		'admin_footer',
		'wp_dashboard_setup',
		'widgets_init',
		'register_sidebar',
		'the_content',
		'the_title',
		'the_excerpt',
		'wp_title',
		'template_redirect',
		'wp',
		'parse_request',
		'pre_get_posts',
		'wp_ajax_',
		'wp_ajax_nopriv_',
		'save_post',
		'delete_post',
		'transition_post_status',
		'add_meta_boxes',
		'manage_posts_columns',
		'restrict_manage_posts',
		'bulk_actions-',
		'shutdown',
		'login_head',
		'login_footer',
		'login_enqueue_scripts',
		'rest_api_init',
		'wp_default_scripts',
		'wp_default_styles',
		'customize_register',
		'customize_preview_init',
		'switch_theme',
		'after_switch_theme',
		'load-',
		'current_screen',
		'admin_print_scripts',
		'admin_print_styles',
		'in_admin_header',
		'wp_before_admin_bar_render',
		'wp_after_admin_bar_render',
	);

	/**
	 * Discovered actions.
	 *
	 * @var array<array<string, mixed>>
	 */
	private array $actions = array();

	/**
	 * Discovered filters.
	 *
	 * @var array<array<string, mixed>>
	 */
	private array $filters = array();

	/**
	 * Discovered REST routes.
	 *
	 * @var array<array<string, mixed>>
	 */
	private array $rest_routes = array();

	/**
	 * Discovered shortcodes.
	 *
	 * @var array<array<string, mixed>>
	 */
	private array $shortcodes = array();

	/**
	 * Files scanned count.
	 */
	private int $files_scanned = 0;

	/**
	 * Files skipped due to errors.
	 */
	private int $files_errored = 0;

	/**
	 * Whether scan was truncated.
	 */
	private bool $truncated = false;

	/**
	 * Total files found.
	 */
	private int $total_files_found = 0;

	/**
	 * Scan a plugin directory for hooks, REST routes, and shortcodes.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory.
	 * @return array<string, mixed> Structured scan results.
	 */
	public function scan_plugin( string $plugin_dir ): array {
		$start_time = microtime( true );

		// Reset state.
		$this->actions       = array();
		$this->filters       = array();
		$this->rest_routes   = array();
		$this->shortcodes    = array();
		$this->files_scanned = 0;
		$this->files_errored = 0;
		$this->truncated     = false;

		// Find PHP files.
		$files = $this->find_php_files( $plugin_dir );

		// Scan each file.
		foreach ( $files as $filepath ) {
			$this->scan_file( $filepath, $plugin_dir );
		}

		// Deduplicate.
		$this->actions    = $this->deduplicate( $this->actions );
		$this->filters    = $this->deduplicate( $this->filters );
		$this->shortcodes = $this->deduplicate_shortcodes( $this->shortcodes );

		// Filter out core hooks.
		$this->actions = $this->filter_core_hooks( $this->actions );
		$this->filters = $this->filter_core_hooks( $this->filters );

		// Sort alphabetically.
		usort( $this->actions, fn( $a, $b ) => strcmp( $a['hook_name'], $b['hook_name'] ) );
		usort( $this->filters, fn( $a, $b ) => strcmp( $a['hook_name'], $b['hook_name'] ) );
		usort( $this->rest_routes, fn( $a, $b ) => strcmp( $a['full_route'], $b['full_route'] ) );
		usort( $this->shortcodes, fn( $a, $b ) => strcmp( $a['tag'], $b['tag'] ) );

		// Classify discoveries into potential abilities.
		$plugin_slug         = basename( $plugin_dir );
		$potential_abilities = $this->classify_discoveries( $plugin_slug );

		$end_time = microtime( true );

		return array(
			'potential_abilities' => $potential_abilities,
			'actions'             => $this->actions,
			'filters'             => $this->filters,
			'rest_routes'         => $this->rest_routes,
			'shortcodes'          => $this->shortcodes,
			'stats'               => array(
				'files_scanned'            => $this->files_scanned,
				'files_errored'            => $this->files_errored,
				'total_files'              => $this->total_files_found,
				'truncated'                => $this->truncated,
				'total_hooks'              => count( $this->actions ) + count( $this->filters ),
				'total_routes'             => count( $this->rest_routes ),
				'total_shortcodes'         => count( $this->shortcodes ),
				'potential_abilities_count' => count( $potential_abilities ),
				'scan_time_ms'             => round( ( $end_time - $start_time ) * 1000, 1 ),
			),
		);
	}

	/**
	 * Find all PHP files in a directory recursively.
	 *
	 * @param string $dir Directory path.
	 * @return array<string> Array of file paths.
	 */
	private function find_php_files( string $dir ): array {
		$files = array();

		if ( ! is_dir( $dir ) ) {
			return $files;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator(
					new RecursiveDirectoryIterator(
						$dir,
						RecursiveDirectoryIterator::SKIP_DOTS
					),
					function ( SplFileInfo $file, string $key, RecursiveDirectoryIterator $iterator ) {
						// Skip symlinks to prevent scanning outside plugin directory.
						if ( $file->isLink() ) {
							return false;
						}

						// Skip excluded directories.
						if ( $iterator->hasChildren() ) {
							$dirname = $file->getFilename();
							if ( in_array( strtolower( $dirname ), self::EXCLUDED_DIRS, true ) ) {
								return false;
							}
						}
						return true;
					}
				),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && strtolower( $file->getExtension() ) === 'php' ) {
					$files[] = $file->getPathname();
				}
			}
		} catch ( \Exception $e ) {
			// Directory iteration failed, return empty.
			return $files;
		}

		$this->total_files_found = count( $files );

		// Cap at max files.
		if ( count( $files ) > self::MAX_FILES ) {
			$files           = array_slice( $files, 0, self::MAX_FILES );
			$this->truncated = true;
		}

		return $files;
	}

	/**
	 * Scan a single PHP file for function calls.
	 *
	 * @param string $filepath   Absolute path to the file.
	 * @param string $plugin_dir Plugin base directory for relative path calculation.
	 */
	private function scan_file( string $filepath, string $plugin_dir ): void {
		// Skip files that are too large.
		$filesize = filesize( $filepath );
		if ( false === $filesize || $filesize > self::MAX_FILE_SIZE ) {
			return;
		}

		$source = file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $source ) {
			++$this->files_errored;
			return;
		}

		try {
			$tokens = token_get_all( $source, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			++$this->files_errored;
			return;
		}

		++$this->files_scanned;

		$relative_path = str_replace( trailingslashit( $plugin_dir ), '', $filepath );
		$token_count   = count( $tokens );

		$target_functions = array(
			'do_action'          => 'action',
			'do_action_ref_array' => 'action',
			'apply_filters'      => 'filter',
			'apply_filters_ref_array' => 'filter',
			'register_rest_route' => 'rest_route',
			'add_shortcode'      => 'shortcode',
		);

		for ( $i = 0; $i < $token_count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) ) {
				continue;
			}

			$token_type  = $tokens[ $i ][0];
			$token_value = $tokens[ $i ][1];
			$token_line  = $tokens[ $i ][2];

			if ( T_STRING !== $token_type ) {
				continue;
			}

			if ( ! isset( $target_functions[ $token_value ] ) ) {
				continue;
			}

			// Verify this is not a method call ($this->do_action or Class::do_action).
			// Exception: register_rest_route can be called as a method.
			if ( 'register_rest_route' !== $token_value ) {
				$prev = $this->get_prev_meaningful_token( $tokens, $i );
				if ( $prev && is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR ), true ) ) {
					continue;
				}
			}

			// Also handle namespaced calls: \do_action().
			$prev = $this->get_prev_meaningful_token( $tokens, $i );
			// It's fine if previous is T_NS_SEPARATOR — that's \do_action() which is valid.

			// Find the opening parenthesis.
			$j = $i + 1;
			while ( $j < $token_count && $this->is_whitespace_or_comment( $tokens[ $j ] ) ) {
				++$j;
			}

			if ( ! isset( $tokens[ $j ] ) || '(' !== $tokens[ $j ] ) {
				continue;
			}

			// Extract arguments.
			$call_type = $target_functions[ $token_value ];
			$args      = $this->extract_arguments( $tokens, $j, $token_count );

			$this->record_discovery( $call_type, $args, $relative_path, $token_line );
		}
	}

	/**
	 * Get the previous meaningful (non-whitespace, non-comment) token.
	 *
	 * @param array<mixed> $tokens Token array.
	 * @param int          $pos    Current position.
	 * @return mixed|null The token or null.
	 */
	private function get_prev_meaningful_token( array $tokens, int $pos ): mixed {
		$j = $pos - 1;
		while ( $j >= 0 ) {
			if ( ! $this->is_whitespace_or_comment( $tokens[ $j ] ) ) {
				return $tokens[ $j ];
			}
			--$j;
		}
		return null;
	}

	/**
	 * Check if a token is whitespace or comment.
	 *
	 * @param mixed $token Token to check.
	 * @return bool
	 */
	private function is_whitespace_or_comment( mixed $token ): bool {
		if ( ! is_array( $token ) ) {
			return false;
		}
		return in_array(
			$token[0],
			array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ),
			true
		);
	}

	/**
	 * Extract arguments from a function call starting at the opening parenthesis.
	 *
	 * @param array<mixed> $tokens    Token array.
	 * @param int          $open_pos  Position of '('.
	 * @param int          $count     Total token count.
	 * @return array<array<mixed>> Array of argument token arrays.
	 */
	private function extract_arguments( array $tokens, int $open_pos, int $count ): array {
		$depth              = 0;
		$args               = array();
		$current_arg_tokens = array();

		for ( $i = $open_pos; $i < $count; $i++ ) {
			$t = $tokens[ $i ];

			if ( $t === '(' ) {
				++$depth;
				if ( $depth > 1 ) {
					$current_arg_tokens[] = $t;
				}
				continue;
			}

			if ( $t === ')' ) {
				--$depth;
				if ( 0 === $depth ) {
					if ( ! empty( $current_arg_tokens ) ) {
						$args[] = $current_arg_tokens;
					}
					break;
				}
				$current_arg_tokens[] = $t;
				continue;
			}

			if ( $t === ',' && 1 === $depth ) {
				$args[] = $current_arg_tokens;
				$current_arg_tokens = array();
				continue;
			}

			if ( $depth >= 1 ) {
				$current_arg_tokens[] = $t;
			}
		}

		return $args;
	}

	/**
	 * Record a discovered function call.
	 *
	 * @param string               $call_type     Type: 'action', 'filter', 'rest_route', 'shortcode'.
	 * @param array<array<mixed>>  $args          Extracted arguments.
	 * @param string               $relative_path File path relative to plugin dir.
	 * @param int                  $line          Line number.
	 */
	private function record_discovery( string $call_type, array $args, string $relative_path, int $line ): void {
		if ( empty( $args ) ) {
			return;
		}

		switch ( $call_type ) {
			case 'action':
			case 'filter':
				$this->record_hook( $call_type, $args, $relative_path, $line );
				break;

			case 'rest_route':
				$this->record_rest_route( $args, $relative_path, $line );
				break;

			case 'shortcode':
				$this->record_shortcode( $args, $relative_path, $line );
				break;
		}
	}

	/**
	 * Record a hook (action or filter) discovery.
	 *
	 * @param string               $type          'action' or 'filter'.
	 * @param array<array<mixed>>  $args          Extracted arguments.
	 * @param string               $relative_path Relative file path.
	 * @param int                  $line          Line number.
	 */
	private function record_hook( string $type, array $args, string $relative_path, int $line ): void {
		$first_arg = $this->extract_string_from_tokens( $args[0] );

		if ( null === $first_arg ) {
			// Dynamic hook name — try to extract static prefix.
			$prefix = $this->extract_dynamic_prefix( $args[0] );
			if ( null !== $prefix && strlen( $prefix ) > 2 ) {
				$record = array(
					'hook_name'   => $prefix . '*',
					'file'        => $relative_path,
					'line'        => $line,
					'param_count' => max( 0, count( $args ) - 1 ),
					'dynamic'     => true,
				);

				if ( 'action' === $type ) {
					$this->actions[] = $record;
				} else {
					$this->filters[] = $record;
				}
			}
			return;
		}

		$record = array(
			'hook_name'   => $first_arg,
			'file'        => $relative_path,
			'line'        => $line,
			'param_count' => max( 0, count( $args ) - 1 ),
			'dynamic'     => false,
		);

		if ( 'action' === $type ) {
			$this->actions[] = $record;
		} else {
			$this->filters[] = $record;
		}
	}

	/**
	 * Record a REST route discovery.
	 *
	 * @param array<array<mixed>>  $args          Extracted arguments.
	 * @param string               $relative_path Relative file path.
	 * @param int                  $line          Line number.
	 */
	private function record_rest_route( array $args, string $relative_path, int $line ): void {
		if ( count( $args ) < 2 ) {
			return;
		}

		$namespace = $this->extract_string_from_tokens( $args[0] );
		$route     = $this->extract_string_from_tokens( $args[1] );

		if ( null === $namespace || null === $route ) {
			return;
		}

		$this->rest_routes[] = array(
			'namespace'  => $namespace,
			'route'      => $route,
			'full_route' => $namespace . $route,
			'file'       => $relative_path,
			'line'       => $line,
		);
	}

	/**
	 * Record a shortcode discovery.
	 *
	 * @param array<array<mixed>>  $args          Extracted arguments.
	 * @param string               $relative_path Relative file path.
	 * @param int                  $line          Line number.
	 */
	private function record_shortcode( array $args, string $relative_path, int $line ): void {
		$tag = $this->extract_string_from_tokens( $args[0] );

		if ( null === $tag ) {
			return;
		}

		$this->shortcodes[] = array(
			'tag'  => $tag,
			'file' => $relative_path,
			'line' => $line,
		);
	}

	/**
	 * Extract a string value from an argument's token array.
	 * Returns null if the argument is not a simple string literal.
	 *
	 * @param array<mixed> $tokens Argument tokens.
	 * @return string|null
	 */
	private function extract_string_from_tokens( array $tokens ): ?string {
		// Filter out whitespace.
		$meaningful = array_values(
			array_filter(
				$tokens,
				fn( $t ) => ! $this->is_whitespace_or_comment( $t )
			)
		);

		if ( count( $meaningful ) !== 1 ) {
			return null;
		}

		$token = $meaningful[0];

		if ( ! is_array( $token ) ) {
			return null;
		}

		if ( T_CONSTANT_ENCAPSED_STRING !== $token[0] ) {
			return null;
		}

		// Strip quotes.
		$value = $token[1];
		return trim( $value, "\"'" );
	}

	/**
	 * Extract a static prefix from a dynamic (concatenated/interpolated) hook name.
	 *
	 * @param array<mixed> $tokens Argument tokens.
	 * @return string|null The static prefix, or null.
	 */
	private function extract_dynamic_prefix( array $tokens ): ?string {
		$prefix = '';

		foreach ( $tokens as $token ) {
			if ( $this->is_whitespace_or_comment( $token ) ) {
				continue;
			}

			if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
				$prefix .= trim( $token[1], "\"'" );
				continue;
			}

			// If we hit a concat operator, continue collecting.
			if ( $token === '.' ) {
				continue;
			}

			// If we hit the start of an encapsed string, extract the leading literal part.
			if ( is_array( $token ) && T_ENCAPSED_AND_WHITESPACE === $token[0] ) {
				$prefix .= $token[1];
				break;
			}

			// Any other token means the rest is dynamic.
			break;
		}

		return ! empty( $prefix ) ? $prefix : null;
	}

	/**
	 * Deduplicate hook records by hook_name, keeping the first occurrence.
	 *
	 * @param array<array<string, mixed>> $records Records to deduplicate.
	 * @return array<array<string, mixed>> Deduplicated records.
	 */
	private function deduplicate( array $records ): array {
		$seen    = array();
		$result  = array();

		foreach ( $records as $record ) {
			$key = $record['hook_name'];
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ]      = true;
				$record['count']   = 1;
				$result[ $key ]    = $record;
			} else {
				++$result[ $key ]['count'];
			}
		}

		return array_values( $result );
	}

	/**
	 * Deduplicate shortcode records by tag.
	 *
	 * @param array<array<string, mixed>> $records Records to deduplicate.
	 * @return array<array<string, mixed>> Deduplicated records.
	 */
	private function deduplicate_shortcodes( array $records ): array {
		$seen   = array();
		$result = array();

		foreach ( $records as $record ) {
			$key = $record['tag'];
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$result[]     = $record;
			}
		}

		return $result;
	}

	/**
	 * Filter out WordPress core hooks from the results.
	 *
	 * @param array<array<string, mixed>> $records Hook records.
	 * @return array<array<string, mixed>> Filtered records.
	 */
	private function filter_core_hooks( array $records ): array {
		return array_values(
			array_filter(
				$records,
				function ( array $record ): bool {
					$hook_name = $record['hook_name'];

					foreach ( self::CORE_HOOKS_BLOCKLIST as $blocked ) {
						// Exact match.
						if ( $hook_name === $blocked ) {
							return false;
						}
						// Prefix match (e.g., 'wp_ajax_' blocks 'wp_ajax_my_action').
						if ( str_ends_with( $blocked, '_' ) && str_starts_with( $hook_name, $blocked ) ) {
							return false;
						}
						if ( str_ends_with( $blocked, '-' ) && str_starts_with( $hook_name, $blocked ) ) {
							return false;
						}
					}

					// Also filter hooks that start with common internal prefixes.
					$internal_prefixes = array(
						'admin_print_',
						'admin_head-',
						'admin_footer-',
						'load-',
						'manage_',
						'wp_ajax_',
						'wp_ajax_nopriv_',
					);

					foreach ( $internal_prefixes as $prefix ) {
						if ( str_starts_with( $hook_name, $prefix ) ) {
							return false;
						}
					}

					return true;
				}
			)
		);
	}

	// =========================================================================
	// Ability Classification Engine
	// =========================================================================

	/**
	 * Classify all discoveries into scored potential abilities.
	 *
	 * @param string $plugin_slug The plugin directory name (e.g., 'akismet').
	 * @return array<array<string, mixed>> Scored potential abilities, sorted by score descending.
	 */
	private function classify_discoveries( string $plugin_slug ): array {
		$abilities = array();

		// Score REST routes (always high value).
		foreach ( $this->rest_routes as $route ) {
			$score = $this->score_rest_route( $route, $plugin_slug );
			if ( $score > 0 ) {
				$abilities[] = $this->build_rest_route_ability( $route, $score, $plugin_slug );
			}
		}

		// Score action hooks.
		foreach ( $this->actions as $hook ) {
			$score = $this->score_hook( $hook, $plugin_slug );
			if ( $score > 0 ) {
				$abilities[] = $this->build_hook_ability( $hook, $score, $plugin_slug, 'action' );
			}
		}

		// Score filter hooks.
		foreach ( $this->filters as $hook ) {
			$score = $this->score_hook( $hook, $plugin_slug );
			if ( $score > 0 ) {
				$abilities[] = $this->build_hook_ability( $hook, $score, $plugin_slug, 'filter' );
			}
		}

		// Score shortcodes.
		foreach ( $this->shortcodes as $shortcode ) {
			$score = $this->score_shortcode( $shortcode, $plugin_slug );
			if ( $score > 0 ) {
				$abilities[] = $this->build_shortcode_ability( $shortcode, $score, $plugin_slug );
			}
		}

		// Sort by score descending.
		usort( $abilities, fn( $a, $b ) => $b['score'] <=> $a['score'] );

		return $abilities;
	}

	/**
	 * Score a hook for ability potential.
	 *
	 * @param array<string, mixed> $hook        Hook record.
	 * @param string               $plugin_slug Plugin directory name.
	 * @return int Score (0 or negative = noise).
	 */
	private function score_hook( array $hook, string $plugin_slug ): int {
		$score     = 0;
		$hook_name = $hook['hook_name'];

		// Check for action verbs.
		if ( $this->has_verb( $hook_name, self::TOOL_VERBS ) || $this->has_verb( $hook_name, self::RESOURCE_VERBS ) ) {
			$score += 20;
		}

		// Plugin-namespaced hook (starts with plugin slug prefix).
		if ( $this->is_plugin_namespaced( $hook_name, $plugin_slug ) ) {
			$score += 15;
		}

		// Parameter count bonus.
		$param_count = $hook['param_count'] ?? 0;
		if ( $param_count >= 2 ) {
			$score += 10;
		} elseif ( $param_count >= 1 ) {
			$score += 5;
		}

		// Static hook bonus.
		if ( empty( $hook['dynamic'] ) ) {
			$score += 5;
		} else {
			$score -= 10;
		}

		// Infrastructure penalty.
		if ( $this->is_infrastructure_hook( $hook_name ) ) {
			$score -= 30;
		}

		return $score;
	}

	/**
	 * Score a REST route for ability potential.
	 *
	 * @param array<string, mixed> $route       REST route record.
	 * @param string               $plugin_slug Plugin directory name.
	 * @return int Score.
	 */
	private function score_rest_route( array $route, string $plugin_slug ): int {
		// REST routes are always high value.
		$score = 50;

		// Plugin-namespaced bonus.
		$ns_lower = strtolower( $route['namespace'] ?? '' );
		if ( str_contains( $ns_lower, strtolower( $plugin_slug ) ) ) {
			$score += 15;
		}

		// Static (no regex params) bonus.
		if ( ! str_contains( $route['route'] ?? '', '(?P' ) ) {
			$score += 5;
		}

		return $score;
	}

	/**
	 * Score a shortcode for ability potential.
	 *
	 * @param array<string, mixed> $shortcode   Shortcode record.
	 * @param string               $plugin_slug Plugin directory name.
	 * @return int Score.
	 */
	private function score_shortcode( array $shortcode, string $plugin_slug ): int {
		$score = 30;

		// Plugin-namespaced bonus.
		$tag_lower  = strtolower( $shortcode['tag'] ?? '' );
		$slug_lower = strtolower( str_replace( '-', '_', $plugin_slug ) );
		if ( str_contains( $tag_lower, $slug_lower ) ) {
			$score += 15;
		}

		return $score;
	}

	/**
	 * Build a potential ability record from a REST route.
	 *
	 * @param array<string, mixed> $route       REST route record.
	 * @param int                  $score       Calculated score.
	 * @param string               $plugin_slug Plugin directory name.
	 * @return array<string, mixed> Ability record.
	 */
	private function build_rest_route_ability( array $route, int $score, string $plugin_slug ): array {
		$route_path = $route['route'] ?? '';

		// Clean route for name generation: remove regex params, slashes.
		$clean_route = preg_replace( '/\(\?P<([^>]+)>[^)]+\)/', '$1', $route_path );
		$clean_route = trim( (string) $clean_route, '/' );
		$clean_route = str_replace( '/', '-', $clean_route );

		$suggested_name = $plugin_slug . '/' . ( $clean_route ?: 'api' );
		$suggested_name = $this->sanitize_ability_name( $suggested_name );

		$label = $this->generate_label_from_route( $route_path );

		return array(
			'suggested_name' => $suggested_name,
			'label'          => $label,
			'ability_type'   => 'resource',
			'confidence'     => $this->score_to_confidence( $score ),
			'score'          => $score,
			'source_type'    => 'rest_route',
			'source'         => array(
				'full_route' => $route['full_route'],
				'namespace'  => $route['namespace'],
				'route'      => $route['route'],
				'file'       => $route['file'],
				'line'       => $route['line'],
			),
		);
	}

	/**
	 * Build a potential ability record from a hook.
	 *
	 * @param array<string, mixed> $hook        Hook record.
	 * @param int                  $score       Calculated score.
	 * @param string               $plugin_slug Plugin directory name.
	 * @param string               $hook_type   'action' or 'filter'.
	 * @return array<string, mixed> Ability record.
	 */
	private function build_hook_ability( array $hook, int $score, string $plugin_slug, string $hook_type ): array {
		$hook_name = $hook['hook_name'];

		// Remove plugin prefix for cleaner name.
		$name_part = $this->strip_plugin_prefix( $hook_name, $plugin_slug );

		$suggested_name = $plugin_slug . '/' . str_replace( '_', '-', $name_part );
		$suggested_name = $this->sanitize_ability_name( $suggested_name );

		$label        = $this->generate_label_from_hook( $hook_name, $plugin_slug );
		$ability_type = $this->detect_ability_type( $hook_name, $hook_type );

		return array(
			'suggested_name' => $suggested_name,
			'label'          => $label,
			'ability_type'   => $ability_type,
			'confidence'     => $this->score_to_confidence( $score ),
			'score'          => $score,
			'source_type'    => $hook_type,
			'source'         => array(
				'hook_name'   => $hook_name,
				'file'        => $hook['file'],
				'line'        => $hook['line'],
				'param_count' => $hook['param_count'],
				'dynamic'     => $hook['dynamic'] ?? false,
			),
		);
	}

	/**
	 * Build a potential ability record from a shortcode.
	 *
	 * @param array<string, mixed> $shortcode   Shortcode record.
	 * @param int                  $score       Calculated score.
	 * @param string               $plugin_slug Plugin directory name.
	 * @return array<string, mixed> Ability record.
	 */
	private function build_shortcode_ability( array $shortcode, int $score, string $plugin_slug ): array {
		$tag = $shortcode['tag'];

		$suggested_name = $plugin_slug . '/render-' . str_replace( '_', '-', $tag );
		$suggested_name = $this->sanitize_ability_name( $suggested_name );

		// Title-case the tag.
		$label = 'Render ' . str_replace( array( '_', '-' ), ' ', ucwords( $tag, '_-' ) );

		return array(
			'suggested_name' => $suggested_name,
			'label'          => $label,
			'ability_type'   => 'tool',
			'confidence'     => $this->score_to_confidence( $score ),
			'score'          => $score,
			'source_type'    => 'shortcode',
			'source'         => array(
				'tag'  => $tag,
				'file' => $shortcode['file'],
				'line' => $shortcode['line'],
			),
		);
	}

	/**
	 * Check if a hook name contains any of the given verbs.
	 *
	 * @param string        $hook_name Hook name.
	 * @param array<string> $verbs     Verb list.
	 * @return bool
	 */
	private function has_verb( string $hook_name, array $verbs ): bool {
		$parts = explode( '_', strtolower( $hook_name ) );

		foreach ( $verbs as $verb ) {
			if ( in_array( $verb, $parts, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a hook name matches infrastructure/UI patterns.
	 *
	 * @param string $hook_name Hook name.
	 * @return bool
	 */
	private function is_infrastructure_hook( string $hook_name ): bool {
		$lower = strtolower( $hook_name );

		foreach ( self::INFRASTRUCTURE_SUFFIXES as $suffix ) {
			if ( str_ends_with( $lower, $suffix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get plugin prefix variants for matching.
	 *
	 * @param string $plugin_slug Plugin directory name.
	 * @return array<string> Prefix variants, longest first.
	 */
	private function get_plugin_prefixes( string $plugin_slug ): array {
		$prefixes = array(
			str_replace( '-', '_', $plugin_slug ) . '_',
		);

		// Add shorter suffix variant (e.g., "crontrol_" for "wp-crontrol").
		$slug_parts = explode( '-', $plugin_slug );
		if ( count( $slug_parts ) > 1 ) {
			$prefixes[] = end( $slug_parts ) . '_';
		}

		// Sort longest first for greedy matching.
		usort( $prefixes, fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );

		return $prefixes;
	}

	/**
	 * Check if a hook name is namespaced to this plugin.
	 *
	 * @param string $hook_name   Hook name.
	 * @param string $plugin_slug Plugin directory name.
	 * @return bool
	 */
	private function is_plugin_namespaced( string $hook_name, string $plugin_slug ): bool {
		foreach ( $this->get_plugin_prefixes( $plugin_slug ) as $prefix ) {
			if ( str_starts_with( $hook_name, $prefix ) ) {
				return true;
			}
			// Also check slash variant (e.g., "crontrol/" for "crontrol/added_new_schedule").
			$slash_prefix = rtrim( $prefix, '_' ) . '/';
			if ( str_starts_with( $hook_name, $slash_prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Strip plugin prefix from a hook name.
	 *
	 * @param string $hook_name   Hook name.
	 * @param string $plugin_slug Plugin directory name.
	 * @return string Hook name without plugin prefix.
	 */
	private function strip_plugin_prefix( string $hook_name, string $plugin_slug ): string {
		// Also check for slash-separated prefix (e.g., "crontrol/" for "crontrol/added_new_schedule").
		$slash_prefixes = array();
		foreach ( $this->get_plugin_prefixes( $plugin_slug ) as $prefix ) {
			if ( str_starts_with( $hook_name, $prefix ) ) {
				return substr( $hook_name, strlen( $prefix ) );
			}
			// Build slash variant: "crontrol_" → "crontrol/".
			$slash_prefixes[] = rtrim( $prefix, '_' ) . '/';
		}

		foreach ( $slash_prefixes as $prefix ) {
			if ( str_starts_with( $hook_name, $prefix ) ) {
				return substr( $hook_name, strlen( $prefix ) );
			}
		}

		return $hook_name;
	}

	/**
	 * Detect ability type from a hook name.
	 *
	 * @param string $hook_name Hook name.
	 * @param string $hook_type 'action' or 'filter'.
	 * @return string 'tool' or 'resource'.
	 */
	private function detect_ability_type( string $hook_name, string $hook_type ): string {
		if ( $this->has_verb( $hook_name, self::TOOL_VERBS ) ) {
			return 'tool';
		}

		if ( $this->has_verb( $hook_name, self::RESOURCE_VERBS ) ) {
			return 'resource';
		}

		// Default: actions are tools, filters are resources.
		return 'action' === $hook_type ? 'tool' : 'resource';
	}

	/**
	 * Generate a human-readable label from a hook name.
	 *
	 * @param string $hook_name   Hook name.
	 * @param string $plugin_slug Plugin directory name.
	 * @return string Label.
	 */
	private function generate_label_from_hook( string $hook_name, string $plugin_slug ): string {
		// Reuse strip_plugin_prefix for consistency (handles both _ and / separators).
		$name = $this->strip_plugin_prefix( $hook_name, $plugin_slug );

		// Remove trailing wildcard from dynamic hooks.
		$name = rtrim( $name, '*' );

		// Convert separators to spaces, title case.
		$label = str_replace( array( '_', '/', '-' ), ' ', $name );
		$label = ucwords( $label );

		return trim( $label );
	}

	/**
	 * Generate a human-readable label from a REST route.
	 *
	 * @param string $route Route pattern.
	 * @return string Label.
	 */
	private function generate_label_from_route( string $route ): string {
		// Remove regex params, replace with placeholder names.
		$clean = preg_replace( '/\(\?P<([^>]+)>[^)]+\)/', '{$1}', $route );
		$clean = trim( (string) $clean, '/' );

		if ( empty( $clean ) ) {
			return 'API Root';
		}

		// Convert path segments to readable label.
		$parts = explode( '/', $clean );
		$label = array_map(
			function ( string $part ): string {
				if ( str_starts_with( $part, '{' ) ) {
					return 'by ' . ucfirst( trim( $part, '{}' ) );
				}
				return ucfirst( str_replace( array( '-', '_' ), ' ', $part ) );
			},
			$parts
		);

		return implode( ' ', $label );
	}

	/**
	 * Sanitize an ability name to match the required pattern.
	 *
	 * @param string $name Raw name.
	 * @return string Sanitized name matching /^[a-z0-9-]+\/[a-z0-9-]+$/.
	 */
	private function sanitize_ability_name( string $name ): string {
		$name = strtolower( $name );
		$name = str_replace( '_', '-', $name );

		// Ensure exactly one forward slash.
		$parts = explode( '/', $name, 2 );
		if ( count( $parts ) < 2 || empty( $parts[1] ) ) {
			$parts = array( $parts[0], 'unknown' );
		}

		// Clean each part: only keep a-z, 0-9, hyphens.
		$parts[0] = preg_replace( '/[^a-z0-9-]/', '', $parts[0] );
		$parts[1] = preg_replace( '/[^a-z0-9-]/', '-', $parts[1] );
		$parts[1] = preg_replace( '/-+/', '-', $parts[1] ); // Collapse multiple hyphens.
		$parts[1] = trim( $parts[1], '-' );

		if ( empty( $parts[0] ) ) {
			$parts[0] = 'plugin';
		}
		if ( empty( $parts[1] ) ) {
			$parts[1] = 'unknown';
		}

		return $parts[0] . '/' . $parts[1];
	}

	/**
	 * Map a numeric score to a confidence level.
	 *
	 * @param int $score Numeric score.
	 * @return string 'high', 'medium', or 'low'.
	 */
	private function score_to_confidence( int $score ): string {
		if ( $score >= 60 ) {
			return 'high';
		}
		if ( $score >= 30 ) {
			return 'medium';
		}
		return 'low';
	}
}
