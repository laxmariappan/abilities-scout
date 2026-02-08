/**
 * Abilities Scout Admin JavaScript
 *
 * @package Abilities_Scout
 * @license GPL-2.0-or-later
 *
 * This file is part of Abilities Scout.
 *
 * Abilities Scout is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * Abilities Scout is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		AbilitiesScout.init();
	});

	const AbilitiesScout = {

		lastScanData: null,

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$('#abilities-scout-scan-btn').on('click', this.scanPlugin.bind(this));
		},

		scanPlugin: function() {
			const pluginSlug = $('#abilities-scout-plugin-select').val();

			if (!pluginSlug) {
				this.showNotice('error', abilitiesScout.strings.selectPlugin);
				return;
			}

			const $btn = $('#abilities-scout-scan-btn');
			const $loading = $('#abilities-scout-loading');
			const $results = $('#abilities-scout-results');

			// Show loading state.
			$btn.prop('disabled', true);
			$loading.show();
			$results.hide().empty();

			$.ajax({
				url: abilitiesScout.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abilities_scout_scan',
					nonce: abilitiesScout.nonce,
					plugin: pluginSlug
				},
				success: function(response) {
					if (response.success) {
						AbilitiesScout.renderResults(response.data);
					} else {
						AbilitiesScout.showNotice('error', response.data.message || abilitiesScout.strings.error);
					}
				},
				error: function() {
					AbilitiesScout.showNotice('error', abilitiesScout.strings.error);
				},
				complete: function() {
					$btn.prop('disabled', false);
					$loading.hide();
				}
			});
		},

		renderResults: function(data) {
			this.lastScanData = data;

			const $results = $('#abilities-scout-results');
			const discovered = data.discovered;
			const abilities = discovered.potential_abilities || [];
			let html = '';

			// Plugin info header.
			html += this.renderPluginInfo(data.plugin_info);

			// Stats bar.
			html += this.renderStats(data);

			// Export toolbar.
			html += this.renderExportToolbar();

			// Potential Abilities section (the main value).
			html += this.renderPotentialAbilities(abilities);

			// All Discoveries section (collapsible raw view).
			html += this.renderDiscoveredSection(discovered);

			// Footer.
			html += this.renderFooter();

			$results.html(html).show();
			this.initTabs();
			this.initCollapsible();
			this.bindExportEvents();

			// Scroll to results.
			$('html, body').animate({
				scrollTop: $results.offset().top - 50
			}, 400);
		},

		renderPluginInfo: function(info) {
			let authorHtml = this.escapeHtml(info.author);
			if (info.url && /^https?:\/\//i.test(info.url)) {
				authorHtml = '<a href="' + this.escapeHtml(info.url) + '" target="_blank" rel="noopener noreferrer">' + authorHtml + '</a>';
			}

			return '<div class="abilities-scout-plugin-info">' +
				'<h2>' + this.escapeHtml(info.name) +
				(info.version ? ' <small>v' + this.escapeHtml(info.version) + '</small>' : '') +
				'</h2>' +
				'<p>By ' + authorHtml + '</p>' +
				'</div>';
		},

		renderStats: function(data) {
			const discovered = data.discovered;
			const stats = discovered.stats;
			const abilitiesCount = stats.potential_abilities_count || 0;

			let html = '<div class="abilities-scout-stats">';

			// Highlighted potential abilities stat.
			html += '<div class="abilities-scout-stat-card abilities-scout-stat-highlight">' +
				'<div class="abilities-scout-stat-number">' + abilitiesCount + '</div>' +
				'<div class="abilities-scout-stat-label">Potential Abilities</div>' +
				'</div>';

			html += this.renderStatCard(discovered.actions.length, 'Actions');
			html += this.renderStatCard(discovered.filters.length, 'Filters');
			html += this.renderStatCard(discovered.rest_routes.length, 'REST Routes');
			html += this.renderStatCard(stats.files_scanned, 'Files Scanned');

			if (stats.scan_time_ms) {
				html += this.renderStatCard(stats.scan_time_ms + 'ms', 'Scan Time');
			}

			html += '</div>';

			if (stats.truncated) {
				html += '<div class="notice notice-warning inline"><p>' +
					this.escapeHtml('Partial scan: ' + stats.files_scanned + ' of ' + stats.total_files + ' files analyzed. Results may be incomplete.') +
					'</p></div>';
			}

			return html;
		},

		renderStatCard: function(number, label) {
			return '<div class="abilities-scout-stat-card">' +
				'<div class="abilities-scout-stat-number">' + this.escapeHtml(String(number)) + '</div>' +
				'<div class="abilities-scout-stat-label">' + this.escapeHtml(label) + '</div>' +
				'</div>';
		},

		// =====================================================================
		// Potential Abilities Section
		// =====================================================================

		renderPotentialAbilities: function(abilities) {
			// Only show high and medium confidence.
			const meaningful = abilities.filter(function(a) {
				return a.confidence === 'high' || a.confidence === 'medium';
			});

			let html = '<div class="abilities-scout-section">';
			html += '<h3>' + this.escapeHtml(abilitiesScout.strings.potentialAbilities) + '</h3>';

			if (meaningful.length === 0) {
				html += '<p class="abilities-scout-empty">' +
					this.escapeHtml(abilitiesScout.strings.noPotential) + '</p>';
				html += '</div>';
				return html;
			}

			html += '<p class="description">These hooks and routes scored highest as potential abilities based on naming patterns, parameter counts, and API structure.</p>';
			html += '<div class="abilities-scout-ability-grid">';

			meaningful.forEach(function(ability) {
				html += AbilitiesScout.renderAbilityCard(ability);
			});

			html += '</div></div>';
			return html;
		},

		renderAbilityCard: function(ability) {
			let html = '<div class="abilities-scout-ability-card">';

			// Badges row.
			html += '<div class="abilities-scout-card-badges">';
			html += '<span class="scout-badge scout-badge-' + this.escapeHtml(ability.confidence) + '">' +
				this.escapeHtml(ability.confidence) + '</span>';
			html += '<span class="scout-badge scout-type-' + this.escapeHtml(ability.ability_type) + '">' +
				this.escapeHtml(ability.ability_type) + '</span>';
			html += '<span class="scout-badge scout-source-' + this.escapeHtml(ability.source_type) + '">' +
				this.escapeHtml(ability.source_type.replace('_', ' ')) + '</span>';
			html += '</div>';

			// Label and suggested name.
			html += '<h4>' + this.escapeHtml(ability.label) + '</h4>';
			html += '<code class="abilities-scout-ability-name">' +
				this.escapeHtml(ability.suggested_name) + '</code>';

			// Source details.
			html += '<div class="abilities-scout-card-source">';
			if (ability.source_type === 'rest_route') {
				html += '<span class="scout-source-detail">' +
					this.escapeHtml(ability.source.full_route) + '</span>';
			} else if (ability.source_type === 'shortcode') {
				html += '<span class="scout-source-detail">[' +
					this.escapeHtml(ability.source.tag) + ']</span>';
			} else {
				html += '<span class="scout-source-detail">' +
					this.escapeHtml(ability.source.hook_name) + '</span>';
				if (ability.source.param_count > 0) {
					html += '<span class="scout-param-count">' +
						ability.source.param_count + ' param' +
						(ability.source.param_count > 1 ? 's' : '') + '</span>';
				}
			}
			html += '<span class="scout-file-ref">' +
				this.escapeHtml(ability.source.file) + ':' + ability.source.line + '</span>';
			html += '</div>';

			html += '</div>';
			return html;
		},

		// =====================================================================
		// All Discoveries Section (Collapsible)
		// =====================================================================

		renderDiscoveredSection: function(discovered) {
			const totalDiscoveries = discovered.actions.length + discovered.filters.length +
				discovered.rest_routes.length + discovered.shortcodes.length;

			const tabs = [
				{ key: 'actions', label: 'Actions', count: discovered.actions.length },
				{ key: 'filters', label: 'Filters', count: discovered.filters.length },
				{ key: 'rest_routes', label: 'REST Routes', count: discovered.rest_routes.length },
				{ key: 'shortcodes', label: 'Shortcodes', count: discovered.shortcodes.length }
			];

			let html = '<div class="abilities-scout-section abilities-scout-collapsible">';

			// Collapsible toggle.
			html += '<button class="abilities-scout-toggle" type="button">';
			html += '<span class="abilities-scout-toggle-icon dashicons dashicons-arrow-right-alt2"></span>';
			html += this.escapeHtml(abilitiesScout.strings.allDiscoveries) +
				' <span class="abilities-scout-toggle-count">(' + totalDiscoveries + ' items: ' +
				discovered.actions.length + ' actions, ' +
				discovered.filters.length + ' filters, ' +
				discovered.rest_routes.length + ' routes, ' +
				discovered.shortcodes.length + ' shortcodes)</span>';
			html += '</button>';

			// Collapsible content.
			html += '<div class="abilities-scout-collapsible-content" style="display: none;">';

			html += '<p class="description">Raw discoveries from static code analysis (' +
				this.escapeHtml(String(discovered.stats.files_scanned)) + ' files scanned in ' +
				this.escapeHtml(String(discovered.stats.scan_time_ms)) + 'ms).</p>';

			// Tab headers.
			html += '<div class="abilities-scout-tabs">';
			tabs.forEach(function(tab, index) {
				html += '<button class="abilities-scout-tab' + (index === 0 ? ' active' : '') +
					'" data-tab="' + tab.key + '">' +
					AbilitiesScout.escapeHtml(tab.label) + ' (' + tab.count + ')</button>';
			});
			html += '</div>';

			// Tab contents.
			html += this.renderHookTable('actions', discovered.actions, true);
			html += this.renderHookTable('filters', discovered.filters, false);
			html += this.renderRestRouteTable(discovered.rest_routes);
			html += this.renderShortcodeTable(discovered.shortcodes);

			html += '</div></div>';
			return html;
		},

		renderHookTable: function(key, hooks, isFirst) {
			let html = '<div class="abilities-scout-tab-content' + (isFirst ? ' active' : '') +
				'" data-tab="' + key + '">';

			if (hooks.length === 0) {
				html += '<p class="abilities-scout-empty">No ' + key + ' discovered.</p>';
				html += '</div>';
				return html;
			}

			html += '<table class="abilities-scout-hooks-table widefat striped">';
			html += '<thead><tr>';
			html += '<th>Hook Name</th>';
			html += '<th>File</th>';
			html += '<th>Line</th>';
			html += '<th>Params</th>';
			html += '<th>Occurrences</th>';
			html += '</tr></thead><tbody>';

			hooks.forEach(function(hook) {
				const dynamicClass = hook.dynamic ? ' class="scout-dynamic-hook"' : '';
				html += '<tr' + dynamicClass + '>';
				html += '<td><code>' + AbilitiesScout.escapeHtml(hook.hook_name) + '</code>';
				if (hook.dynamic) {
					html += ' <span class="scout-badge scout-badge-dynamic">dynamic</span>';
				}
				html += '</td>';
				html += '<td>' + AbilitiesScout.escapeHtml(hook.file) + '</td>';
				html += '<td>' + hook.line + '</td>';
				html += '<td>' + hook.param_count + '</td>';
				html += '<td>' + (hook.count || 1) + '</td>';
				html += '</tr>';
			});

			html += '</tbody></table></div>';
			return html;
		},

		renderRestRouteTable: function(routes) {
			let html = '<div class="abilities-scout-tab-content" data-tab="rest_routes">';

			if (routes.length === 0) {
				html += '<p class="abilities-scout-empty">No REST routes discovered.</p>';
				html += '</div>';
				return html;
			}

			html += '<table class="abilities-scout-hooks-table widefat striped">';
			html += '<thead><tr>';
			html += '<th>Route</th>';
			html += '<th>Namespace</th>';
			html += '<th>File</th>';
			html += '<th>Line</th>';
			html += '</tr></thead><tbody>';

			routes.forEach(function(route) {
				html += '<tr>';
				html += '<td><code>' + AbilitiesScout.escapeHtml(route.full_route) + '</code></td>';
				html += '<td>' + AbilitiesScout.escapeHtml(route.namespace) + '</td>';
				html += '<td>' + AbilitiesScout.escapeHtml(route.file) + '</td>';
				html += '<td>' + route.line + '</td>';
				html += '</tr>';
			});

			html += '</tbody></table></div>';
			return html;
		},

		renderShortcodeTable: function(shortcodes) {
			let html = '<div class="abilities-scout-tab-content" data-tab="shortcodes">';

			if (shortcodes.length === 0) {
				html += '<p class="abilities-scout-empty">No shortcodes discovered.</p>';
				html += '</div>';
				return html;
			}

			html += '<table class="abilities-scout-hooks-table widefat striped">';
			html += '<thead><tr>';
			html += '<th>Shortcode</th>';
			html += '<th>File</th>';
			html += '<th>Line</th>';
			html += '</tr></thead><tbody>';

			shortcodes.forEach(function(sc) {
				html += '<tr>';
				html += '<td><code>[' + AbilitiesScout.escapeHtml(sc.tag) + ']</code></td>';
				html += '<td>' + AbilitiesScout.escapeHtml(sc.file) + '</td>';
				html += '<td>' + sc.line + '</td>';
				html += '</tr>';
			});

			html += '</tbody></table></div>';
			return html;
		},

		// =====================================================================
		// Footer & Utilities
		// =====================================================================

		renderFooter: function() {
			return '<div class="abilities-scout-footer">' +
				'<p>Found something interesting? ' +
				'<a href="https://github.com/laxmariappan/abilities-scout/issues" target="_blank" rel="noopener noreferrer">' +
				'Share your feedback on GitHub</a></p>' +
				'</div>';
		},

		initTabs: function() {
			$('.abilities-scout-tab').on('click', function() {
				const tabKey = $(this).data('tab');
				const $section = $(this).closest('.abilities-scout-section');

				$section.find('.abilities-scout-tab').removeClass('active');
				$(this).addClass('active');

				$section.find('.abilities-scout-tab-content').removeClass('active');
				$section.find('.abilities-scout-tab-content[data-tab="' + tabKey + '"]').addClass('active');
			});
		},

		initCollapsible: function() {
			$('.abilities-scout-toggle').on('click', function() {
				const $content = $(this).siblings('.abilities-scout-collapsible-content');
				const $icon = $(this).find('.abilities-scout-toggle-icon');

				$content.slideToggle(200);
				$icon.toggleClass('dashicons-arrow-right-alt2 dashicons-arrow-down-alt2');
			});
		},

		// =====================================================================
		// Export Feature
		// =====================================================================

		renderExportToolbar: function() {
			return '<div class="abilities-scout-export-toolbar">' +
				'<span class="abilities-scout-export-label">' +
				this.escapeHtml(abilitiesScout.strings.exportLabel) + '</span>' +
				'<button type="button" class="button abilities-scout-export-btn" data-format="markdown">' +
				'<span class="dashicons dashicons-media-text"></span> Markdown</button>' +
				'<button type="button" class="button abilities-scout-export-btn" data-format="json">' +
				'<span class="dashicons dashicons-editor-code"></span> JSON</button>' +
				'</div>';
		},

		bindExportEvents: function() {
			var self = this;
			$('.abilities-scout-export-btn').off('click').on('click', function() {
				var format = $(this).data('format');
				self.exportResults(format);
			});
		},

		exportResults: function(format) {
			if (!this.lastScanData) {
				return;
			}

			var content, mimeType, extension;

			if (format === 'json') {
				content = this.generateJson(this.lastScanData);
				mimeType = 'application/json';
				extension = 'json';
			} else {
				content = this.generateMarkdown(this.lastScanData);
				mimeType = 'text/markdown';
				extension = 'md';
			}

			var slug = this.slugify(this.lastScanData.plugin_info.name);
			var filename = slug + '-abilities-scout.' + extension;

			this.downloadFile(content, filename, mimeType);
		},

		downloadFile: function(content, filename, mimeType) {
			var blob = new Blob([content], { type: mimeType + ';charset=utf-8' });
			var url = URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = filename;
			link.style.display = 'none';
			document.body.appendChild(link);
			link.click();

			// Cleanup after a brief delay.
			setTimeout(function() {
				document.body.removeChild(link);
				URL.revokeObjectURL(url);
			}, 100);
		},

		slugify: function(text) {
			return String(text)
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '-')
				.replace(/^-+|-+$/g, '')
				.substring(0, 50);
		},

		// =====================================================================
		// JSON Export Generator
		// =====================================================================

		generateJson: function(data) {
			var info = data.plugin_info;
			var discovered = data.discovered;

			var exportData = {
				'$schema': 'abilities-scout/v1',
				'generator': 'Abilities Scout ' + (abilitiesScout.version || '1.0.0'),
				'exported_at': new Date().toISOString(),
				'plugin': {
					'name': info.name,
					'version': info.version || null,
					'author': info.author || null,
					'url': info.url || null
				},
				'scan_stats': {
					'files_scanned': discovered.stats.files_scanned,
					'files_errored': discovered.stats.files_errored,
					'total_files': discovered.stats.total_files,
					'truncated': discovered.stats.truncated,
					'total_hooks': discovered.stats.total_hooks,
					'total_routes': discovered.stats.total_routes,
					'total_shortcodes': discovered.stats.total_shortcodes,
					'potential_abilities_count': discovered.stats.potential_abilities_count,
					'scan_time_ms': discovered.stats.scan_time_ms
				},
				'potential_abilities': (discovered.potential_abilities || []).map(function(a) {
					return {
						'suggested_name': a.suggested_name,
						'label': a.label,
						'ability_type': a.ability_type,
						'confidence': a.confidence,
						'score': a.score,
						'source_type': a.source_type,
						'source': a.source
					};
				}),
				'raw_discoveries': {
					'actions': discovered.actions,
					'filters': discovered.filters,
					'rest_routes': discovered.rest_routes,
					'shortcodes': discovered.shortcodes
				}
			};

			return JSON.stringify(exportData, null, 2);
		},

		// =====================================================================
		// Markdown Export Generator
		// =====================================================================

		generateMarkdown: function(data) {
			var info = data.plugin_info;
			var discovered = data.discovered;
			var abilities = discovered.potential_abilities || [];
			var lines = [];

			// Title.
			lines.push('# Abilities Scout Report: ' + info.name);
			lines.push('');

			// Metadata.
			lines.push('**Plugin:** ' + info.name + (info.version ? ' v' + info.version : ''));
			if (info.author) {
				lines.push('**Author:** ' + info.author);
			}
			if (info.url) {
				lines.push('**URL:** ' + info.url);
			}
			lines.push('**Scanned:** ' + new Date().toISOString().split('T')[0]);
			lines.push('**Generator:** Abilities Scout ' + (abilitiesScout.version || '1.0.0'));
			lines.push('');

			// AI Agent Preamble.
			lines.push('---');
			lines.push('');
			lines.push('## How to Use This Document');
			lines.push('');
			lines.push('This document contains scan results from **Abilities Scout**, which analyzed the ' +
				info.name + ' plugin to discover hooks, REST routes, and shortcodes that could be ' +
				'registered as **abilities** using the WordPress Abilities API.');
			lines.push('');
			lines.push('### What is the Abilities API?');
			lines.push('');
			lines.push('The WordPress Abilities API (WP 6.9+) provides a standardized way to register ' +
				'AI-callable units of functionality. Each ability has a unique name, description, ' +
				'JSON Schema input/output definitions, and an execute callback.');
			lines.push('');
			lines.push('### Registration Pattern');
			lines.push('');
			lines.push('```php');
			lines.push("add_action( 'abilities_api_init', function() {");
			lines.push("    wp_register_ability( 'namespace/ability-name', array(");
			lines.push("        'label'               => __( 'Human-Readable Label', 'text-domain' ),");
			lines.push("        'description'          => __( 'What this ability does, for AI agents.', 'text-domain' ),");
			lines.push("        'input_schema'         => array(");
			lines.push("            'type'       => 'object',");
			lines.push("            'properties' => array(");
			lines.push("                'param_name' => array(");
			lines.push("                    'type'        => 'string',");
			lines.push("                    'description' => 'Parameter description',");
			lines.push("                ),");
			lines.push("            ),");
			lines.push("            'required'             => array( 'param_name' ),");
			lines.push("            'additionalProperties' => false,");
			lines.push("        ),");
			lines.push("        'output_schema'        => array(");
			lines.push("            'type'       => 'object',");
			lines.push("            'properties' => array(");
			lines.push("                'result' => array(");
			lines.push("                    'type'        => 'string',");
			lines.push("                    'description' => 'Result description',");
			lines.push("                ),");
			lines.push("            ),");
			lines.push("        ),");
			lines.push("        'execute_callback'     => 'my_execute_function',");
			lines.push("        'permission_callback'  => function() {");
			lines.push("            return current_user_can( 'manage_options' );");
			lines.push("        },");
			lines.push("    ) );");
			lines.push("} );");
			lines.push('```');
			lines.push('');
			lines.push('**Required arguments:** `label`, `description`, `input_schema`, `output_schema`, `execute_callback`');
			lines.push('');
			lines.push('**Optional:** `permission_callback` (defaults to true), `meta` (arbitrary metadata array)');
			lines.push('');
			lines.push('**Ability Name Pattern:** `namespace/ability-name` (lowercase alphanumeric + hyphens, exactly one forward slash)');
			lines.push('');
			lines.push('**Ability Types:**');
			lines.push('- **tool** -- Performs an action (create, update, delete, send, etc.)');
			lines.push('- **resource** -- Returns data (get, list, check, query, etc.)');
			lines.push('');
			lines.push('### Your Task');
			lines.push('');
			lines.push('Use the potential abilities listed below to generate `wp_register_ability()` code for the ' +
				info.name + ' plugin. Each entry includes a suggested name, type, confidence score, and the source ' +
				'hook/route/shortcode it was derived from. High-confidence items are the strongest candidates.');
			lines.push('');

			// Scan Summary.
			lines.push('---');
			lines.push('');
			lines.push('## Scan Summary');
			lines.push('');
			lines.push('| Metric | Value |');
			lines.push('|--------|-------|');
			lines.push('| Files Scanned | ' + discovered.stats.files_scanned + ' |');
			lines.push('| Total Hooks | ' + discovered.stats.total_hooks + ' |');
			lines.push('| REST Routes | ' + discovered.stats.total_routes + ' |');
			lines.push('| Shortcodes | ' + discovered.stats.total_shortcodes + ' |');
			lines.push('| Potential Abilities | ' + discovered.stats.potential_abilities_count + ' |');
			lines.push('| Scan Time | ' + discovered.stats.scan_time_ms + 'ms |');
			if (discovered.stats.truncated) {
				lines.push('| **Note** | Scan truncated: ' + discovered.stats.files_scanned +
					' of ' + discovered.stats.total_files + ' files |');
			}
			lines.push('');

			// Potential Abilities.
			lines.push('---');
			lines.push('');
			lines.push('## Potential Abilities');
			lines.push('');

			if (abilities.length === 0) {
				lines.push('No potential abilities were discovered in this plugin.');
				lines.push('');
			} else {
				var groups = { high: [], medium: [], low: [] };
				abilities.forEach(function(a) {
					if (groups[a.confidence]) {
						groups[a.confidence].push(a);
					}
				});

				var self = this;
				['high', 'medium', 'low'].forEach(function(level) {
					if (groups[level].length === 0) {
						return;
					}

					lines.push('### ' + level.charAt(0).toUpperCase() + level.slice(1) +
						' Confidence (' + groups[level].length + ')');
					lines.push('');

					groups[level].forEach(function(ability) {
						lines.push('#### ' + ability.label);
						lines.push('');
						lines.push('- **Suggested Name:** `' + ability.suggested_name + '`');
						lines.push('- **Type:** ' + ability.ability_type);
						lines.push('- **Confidence:** ' + ability.confidence + ' (score: ' + ability.score + ')');
						lines.push('- **Source Type:** ' + ability.source_type.replace('_', ' '));

						if (ability.source_type === 'rest_route') {
							lines.push('- **REST Route:** `' + ability.source.full_route + '`');
							lines.push('- **Namespace:** `' + ability.source.namespace + '`');
							lines.push('- **Route Pattern:** `' + ability.source.route + '`');
						} else if (ability.source_type === 'shortcode') {
							lines.push('- **Shortcode:** `[' + ability.source.tag + ']`');
						} else {
							lines.push('- **Hook:** `' + ability.source.hook_name + '`');
							lines.push('- **Parameters:** ' + (ability.source.param_count || 0));
							if (ability.source.dynamic) {
								lines.push('- **Dynamic Hook:** yes (name constructed at runtime)');
							}
						}

						lines.push('- **File:** `' + ability.source.file + ':' + ability.source.line + '`');
						lines.push('');
					});
				});
			}

			// Raw Discoveries.
			lines.push('---');
			lines.push('');
			lines.push('## Raw Discoveries');
			lines.push('');

			// Actions.
			if (discovered.actions.length > 0) {
				lines.push('### Actions (' + discovered.actions.length + ')');
				lines.push('');
				lines.push('| Hook Name | File | Line | Params | Dynamic |');
				lines.push('|-----------|------|------|--------|---------|');
				discovered.actions.forEach(function(h) {
					lines.push('| `' + h.hook_name + '` | ' + h.file + ' | ' +
						h.line + ' | ' + h.param_count + ' | ' + (h.dynamic ? 'yes' : 'no') + ' |');
				});
				lines.push('');
			}

			// Filters.
			if (discovered.filters.length > 0) {
				lines.push('### Filters (' + discovered.filters.length + ')');
				lines.push('');
				lines.push('| Hook Name | File | Line | Params | Dynamic |');
				lines.push('|-----------|------|------|--------|---------|');
				discovered.filters.forEach(function(h) {
					lines.push('| `' + h.hook_name + '` | ' + h.file + ' | ' +
						h.line + ' | ' + h.param_count + ' | ' + (h.dynamic ? 'yes' : 'no') + ' |');
				});
				lines.push('');
			}

			// REST Routes.
			if (discovered.rest_routes.length > 0) {
				lines.push('### REST Routes (' + discovered.rest_routes.length + ')');
				lines.push('');
				lines.push('| Route | Namespace | File | Line |');
				lines.push('|-------|-----------|------|------|');
				discovered.rest_routes.forEach(function(r) {
					lines.push('| `' + r.full_route + '` | ' + r.namespace + ' | ' +
						r.file + ' | ' + r.line + ' |');
				});
				lines.push('');
			}

			// Shortcodes.
			if (discovered.shortcodes.length > 0) {
				lines.push('### Shortcodes (' + discovered.shortcodes.length + ')');
				lines.push('');
				lines.push('| Shortcode | File | Line |');
				lines.push('|-----------|------|------|');
				discovered.shortcodes.forEach(function(s) {
					lines.push('| `[' + s.tag + ']` | ' + s.file + ' | ' + s.line + ' |');
				});
				lines.push('');
			}

			// Footer.
			lines.push('---');
			lines.push('');
			lines.push('*Generated by [Abilities Scout](https://github.com/laxmariappan/abilities-scout)*');

			return lines.join('\n');
		},

		// =====================================================================
		// Notices & Utilities
		// =====================================================================

		showNotice: function(type, message) {
			const validTypes = ['error', 'success', 'warning', 'info'];
			type = validTypes.includes(type) ? type : 'error';

			const $results = $('#abilities-scout-results');
			$results.html(
				'<div class="notice notice-' + type + ' inline"><p>' +
				this.escapeHtml(message) + '</p></div>'
			).show();
		},

		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, function(m) {
				return map[m];
			});
		}
	};

})(jQuery);
