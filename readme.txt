=== Lax Abilities Scout ===
Contributors: lakshmananphp
Tags: abilities, ai, abilities-api, plugin-scanner, hooks
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan installed WordPress plugins and discover potential abilities for the Abilities API.

== Description ==

Lax Abilities Scout scans your installed WordPress plugins and discovers potential "abilities" that could be registered with the WordPress Abilities API.

**How It Works:**

Uses PHP tokenization (`token_get_all()`) to statically analyze plugin source code and extract action hooks, filter hooks, REST API routes, and shortcodes — without executing any scanned code.

A built-in scoring engine classifies every discovery by ability potential:

* REST routes score highest — they are structured APIs already
* Hooks with action verbs (submit, create, delete) are classified as tools
* Hooks with data verbs (get, list, check) are classified as resources
* Infrastructure plumbing (nonces, enqueue, CSS) is filtered out

**Features:**

* Scan any installed plugin with one click
* Smart ability scoring with confidence levels (high, medium, low)
* Suggested ability names and type classification (tool vs resource)
* Export results as Markdown (AI-agent-friendly) or JSON
* Discover action hooks, filter hooks, REST routes, and shortcodes
* See file locations and line numbers for every discovery
* Works as a companion to Abilities Explorer or standalone
* Read-only analysis — never modifies scanned plugins

== Installation ==

1. Upload the `lax-abilities-scout` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Abilities > Scout** in the admin menu.
4. Select a plugin from the dropdown and click **Scout Abilities**.

== Frequently Asked Questions ==

= Does this require the Abilities Explorer plugin? =

No. If Abilities Explorer is active, Scout adds a submenu under the shared "Abilities" menu. If not, Scout creates its own top-level menu.

= Does this require the Abilities API? =

No. Scout is a read-only discovery tool. It identifies *potential* abilities in plugins. It does not require or interact with the Abilities API.

= Does it modify any plugin files? =

No. Scout uses PHP's `token_get_all()` to lexically analyze source code. It only reads files — it never modifies, executes, or includes scanned code.

= What are the export formats? =

Markdown and JSON. The Markdown export includes the full `wp_register_ability()` pattern and is designed to be fed to AI coding tools. The JSON export provides structured data for programmatic use.

== Changelog ==

= 1.2.0 =
* **Enhancement:** Primitives-first scoring — hooks and shortcodes now score higher as ability candidates; REST routes are scored as orchestrators
* **Enhancement:** Scan results split into Primitive Abilities and REST Endpoints sections
* **Fix:** Draft stubs now include the required `category` field and `wp_register_ability_category()` registration step
* **Fix:** `execute_callback` parameter renamed to `$input` to match the Abilities API convention
* **Fix:** `scan_time_ms` now returns an integer instead of a float
* **Enhancement:** JSON export schema updated to `primitives[]` / `orchestrators[]` arrays (v1.2)

= 1.1.1 =
* **Fix:** Add `show_in_rest` meta to expose abilities in WordPress REST API and MCP adapter
* **Fix:** Abilities now properly discoverable by MCP servers via `/wp-json/wp-abilities/v1/abilities` endpoint
* **Enhancement:** All three MCP tools (scan, export, draft) now visible to AI agents

= 1.1.0 =
* **New:** MCP (Model Context Protocol) support - AI agents can now interact with Lax Abilities Scout directly
* **New:** `lax-abilities-scout/scan` ability - Scan plugins and return potential abilities with confidence filtering
* **New:** `lax-abilities-scout/export` ability - Export scan results in JSON or Markdown format
* **New:** `lax-abilities-scout/draft` ability - Generate PHP code stubs for registering discovered abilities
* **Enhancement:** Server-side export generation for programmatic access
* **Enhancement:** Automatic ability registration when WordPress Abilities API is available
* **Enhancement:** Graceful degradation - works perfectly without Abilities API or MCP Adapter

= 1.0.0 =
* Initial release.
* Static code scanner using PHP tokenization.
* Smart ability scoring with confidence levels.
* Markdown and JSON export for AI agents.
* Admin UI with potential abilities cards and collapsible raw view.
* Companion integration with Abilities Explorer.

== Disclaimer ==

This plugin is not affiliated with, endorsed by, or sponsored by the WordPress Foundation or Automattic, Inc.

"WordPress" is a registered trademark of the WordPress Foundation. "Abilities Explorer" is developed by karmatosed and used here solely for interoperability. All other trademarks referenced in this plugin belong to their respective owners.

This plugin performs read-only static analysis of PHP source code. It does not execute, modify, or include any code from scanned plugins.
