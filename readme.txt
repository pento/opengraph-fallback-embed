=== OpenGraph Fallback Embed ===
Contributors: pento
Donate link: https://github.com/sponsors/pento
Tags: embed, opengraph, link, card, block
Requires at least: 6.3
Tested up to: 6.9
Stable tag: 1.3.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author URI: https://pento.net

Provides an embed block and auto-embed fallback based on a site's OpenGraph tags when no other embed handler matches the URL.

== Description ==

OpenGraph Fallback Embed adds a custom Gutenberg block and an auto-embed fallback that renders rich link cards using OpenGraph metadata from any URL.

**Features:**

* **OpenGraph Embed block** — Paste any URL to embed it as a styled card showing the page's title, description, image, and site name.
* **Auto-embed fallback** — When WordPress can't find an oEmbed provider for a URL pasted on its own line, this plugin automatically fetches the page's OpenGraph tags and renders a card instead of a plain link.
* **Graceful degradation** — Falls back to Twitter Card meta tags, then to HTML `<title>` and `<meta name="description">` when OpenGraph tags are missing.
* **Transient caching** — Fetched metadata is cached for 7 days to minimise external requests.

== Frequently Asked Questions ==

= What happens if a URL has no OpenGraph tags? =

The plugin falls back to Twitter Card meta tags, then to the page's `<title>` and `<meta name="description">`. If none of these are available, the original WordPress output is preserved.

= Does this override existing embed providers? =

No. This plugin only activates when WordPress has no other embed handler for the URL.

= Can I customise the card appearance? =

The card uses the `.og-fallback-embed` CSS class. You can override styles in your theme.

== Changelog ==

= 1.3.0 =
* Add support for falling back to OG tags when the Embed block fails.

= 1.2.0 =
* Add custom Gutenberg block for manual OpenGraph embeds.
* Add REST API endpoint for editor previews.
* Improve fallback chain: OG → Twitter Card → HTML meta.

= 1.1.0 =
* Add transient caching for fetched metadata.

= 1.0.0 =
* Initial release with auto-embed fallback.
