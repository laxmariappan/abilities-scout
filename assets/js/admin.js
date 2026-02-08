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
			const $results = $('#abilities-scout-results');
			const discovered = data.discovered;
			const abilities = discovered.potential_abilities || [];
			let html = '';

			// Plugin info header.
			html += this.renderPluginInfo(data.plugin_info);

			// Stats bar.
			html += this.renderStats(data);

			// Potential Abilities section (the main value).
			html += this.renderPotentialAbilities(abilities);

			// All Discoveries section (collapsible raw view).
			html += this.renderDiscoveredSection(discovered);

			// Footer.
			html += this.renderFooter();

			$results.html(html).show();
			this.initTabs();
			this.initCollapsible();

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
