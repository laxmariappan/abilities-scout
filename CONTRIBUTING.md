# Contributing to Abilities Scout

Thanks for your interest in contributing! Here's how you can help.

## Reporting Issues

- Use the [GitHub Issues](https://github.com/laxmariappan/abilities-scout/issues) page
- Include your WordPress version, PHP version, and the plugin you were scanning
- Describe what you expected vs what happened

## Code Contributions

1. Fork the repository
2. Create a feature branch from `main`
3. Make your changes following the existing code style
4. Test on a local WordPress install
5. Submit a pull request

### Code Standards

- Follow WordPress PHP Coding Standards
- Use `token_get_all()` for any source analysis — never `include`, `require`, or `eval` scanned code
- All user-facing strings must be translatable via `__()` or `esc_html_e()`
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`

### Testing

1. Install Abilities Scout on a WordPress site
2. Scan a few plugins (both small and large) to verify results
3. Test with and without Abilities Explorer active

## Trademark Guidance

When referencing third-party products in code, issues, or documentation:

- **WordPress** is a registered trademark of the WordPress Foundation. Use it only to describe compatibility — never to imply endorsement.
- **Plugin names** (Akismet, WP Crontrol, etc.) are trademarks of their respective owners. Use them only for factual interoperability references.
- Do not use trademarks in ways that imply affiliation or sponsorship.

## License

By contributing, you agree that your contributions will be licensed under GPLv2 or later, consistent with the rest of the project.
