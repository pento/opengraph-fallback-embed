<?php
/**
 * Plugin Name: OpenGraph Fallback Embed
 * Description: Provides an embed block based on a site's OpenGraph tags when no other embed handler matches the URL.
 * Version: 1.3.3
 * Author: pento
 * License: GPL-2.0-or-later
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Text Domain: opengraph-fallback-embed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core class: fetches, parses, caches, and renders OpenGraph cards.
 */
class OpenGraph_Fallback_Embed {

	/** @var int Cache duration in seconds (default 7 days). */
	const CACHE_TTL = 604800;

	/* ------------------------------------------------------------------
	 * Bootstrap
	 * ----------------------------------------------------------------*/

	public static function register(): void {
		// Fallback for auto-embeds (paste a URL on its own line in Classic or blocks).
		add_filter( 'embed_maybe_make_link', [ __CLASS__, 'maybe_embed' ], 99 );

		// Fallback for core/embed block — editor path (oEmbed proxy REST endpoint).
		add_filter( 'rest_request_after_callbacks', [ __CLASS__, 'intercept_oembed_proxy' ], 10, 3 );

		// Fallback for core/embed block — frontend path.
		add_filter( 'pre_oembed_result', [ __CLASS__, 'intercept_pre_oembed' ], 99, 3 );

		// Register the Gutenberg block.
		add_action( 'init', [ __CLASS__, 'register_block' ] );
	}

	/* ------------------------------------------------------------------
	 * Auto-embed fallback
	 * ----------------------------------------------------------------*/

	/**
	 * Intercept the plain-link fallback and try to return an OG card instead.
	 *
	 * @param string $output The markup WordPress was about to return (a bare <a> tag).
	 * @return string Embed card HTML or the original output on failure.
	 */
	public static function maybe_embed( string $output ): string {
		if ( ! preg_match( '#https?://[^\s"<>]+#i', $output, $matches ) ) {
			return $output;
		}

		$url = $matches[0];
		$og  = self::get_og_data( $url );

		if ( empty( $og['title'] ) ) {
			return $output;
		}

		return self::render_card( $og, $url );
	}

	/* ------------------------------------------------------------------
	 * Embed block fallback — editor path
	 * ----------------------------------------------------------------*/

	/**
	 * Intercept the oEmbed proxy REST response and return an OG card
	 * when the proxy fails to resolve a URL.
	 *
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result from the endpoint callback.
	 * @param array                                            $handler  Route handler info.
	 * @param WP_REST_Request                                  $request  The request object.
	 * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed
	 */
	public static function intercept_oembed_proxy( $response, $handler, $request ) {
		if ( $request->get_route() !== '/oembed/1.0/proxy' ) {
			return $response;
		}

		// The proxy returns WP_Error on initial failure, or the cached
		// sentinel string '{{unknown}}' on subsequent requests.
		if ( ! is_wp_error( $response ) && '{{unknown}}' !== $response ) {
			return $response;
		}

		$url = $request->get_param( 'url' );

		if ( empty( $url ) ) {
			return $response;
		}

		$og = self::get_og_data( $url );

		if ( empty( $og['title'] ) ) {
			return $response;
		}

		$site_name = ! empty( $og['site_name'] ) ? $og['site_name'] : wp_parse_url( $url, PHP_URL_HOST );

		// The editor renders "rich" embeds inside a sandboxed iframe,
		// so styles must be inlined.
		$css_file  = plugin_dir_path( __FILE__ ) . 'build/og-embed/style-index.css';
		$css       = file_exists( $css_file ) ? file_get_contents( $css_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$card_html = self::render_card( $og, $url );

		$styled_html = '';
		if ( $css ) {
			$styled_html .= '<style>' . $css . '</style>';
		}
		$styled_html .= $card_html;

		return (object) [
			'version'       => '1.0',
			'type'          => 'rich',
			'provider_name' => $site_name,
			'provider_url'  => $url,
			'html'          => $styled_html,
			'width'         => 600,
			'height'        => null,
		];
	}

	/* ------------------------------------------------------------------
	 * Embed block fallback — frontend path
	 * ----------------------------------------------------------------*/

	/**
	 * Provide an OG card when wp_oembed_get() has no result for a URL.
	 *
	 * @param string|null $result The oEmbed HTML result, or null if not yet handled.
	 * @param string      $url    The URL being embedded.
	 * @param array       $args   Additional arguments.
	 * @return string|null Card HTML, or null to let WordPress continue.
	 */
	public static function intercept_pre_oembed( $result, $url, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Another filter already provided a result.
		if ( null !== $result ) {
			return $result;
		}

		// If a registered oEmbed provider exists, let WordPress handle it.
		$oembed   = _wp_oembed_get_object();
		$provider = $oembed->get_provider( $url, [ 'discover' => false ] );

		if ( $provider ) {
			return null;
		}

		$og = self::get_og_data( $url );

		if ( empty( $og['title'] ) ) {
			return null;
		}

		return self::render_card( $og, $url );
	}

	/* ------------------------------------------------------------------
	 * Gutenberg block
	 * ----------------------------------------------------------------*/

	public static function register_block(): void {
		// block.json's file: references handle script and style registration.
		// The "style" field loads style.css in the editor iframe AND on the
		// front end; "editorStyle" loads editor.css in the iframe and the
		// editor chrome.
		register_block_type(
			plugin_dir_path( __FILE__ ) . 'build/og-embed',
			[
				'render_callback' => [ __CLASS__, 'render_block' ],
			]
		);
	}

	/**
	 * Server-side render callback for the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML markup.
	 */
	public static function render_block( array $attributes ): string {
		$url = $attributes['url'] ?? '';

		if ( empty( $url ) ) {
			return '';
		}

		$og = self::get_og_data( $url );

		if ( empty( $og['title'] ) ) {
			return sprintf(
				'<p class="og-fallback-embed__error">%s</p>',
				esc_html__( 'Could not retrieve OpenGraph data for this URL.', 'opengraph-fallback-embed' )
			);
		}

		return self::render_card( $og, $url );
	}

	/* ------------------------------------------------------------------
	 * REST endpoint — used by the editor for instant previews
	 * ----------------------------------------------------------------*/

	public static function register_rest_route(): void {
		register_rest_route(
			'og-fallback-embed/v1',
			'/preview',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'rest_preview' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'url' => [
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => function ( $value ) {
							return filter_var( $value, FILTER_VALIDATE_URL ) !== false;
						},
					],
				],
			]
		);
	}

	/**
	 * Return parsed OG data as JSON for the editor preview.
	 */
	public static function rest_preview( WP_REST_Request $request ): WP_REST_Response {
		$url = $request->get_param( 'url' );
		$og  = self::get_og_data( $url );

		return new WP_REST_Response( $og, 200 );
	}

	/* ------------------------------------------------------------------
	 * OpenGraph fetching / parsing
	 * ----------------------------------------------------------------*/

	/**
	 * Fetch and parse OpenGraph tags for a URL, with transient caching.
	 *
	 * @param string $url The page URL.
	 * @return array<string, string> Associative array of OG properties.
	 */
	public static function get_og_data( string $url ): array {
		$cache_key = 'og_embed_' . md5( $url );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_safe_remote_get(
			$url,
			[
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			]
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return [];
		}

		$html = wp_remote_retrieve_body( $response );
		$og   = self::parse_og_tags( $html, $url );

		set_transient( $cache_key, $og, self::CACHE_TTL );

		return $og;
	}

	/**
	 * Extract OpenGraph (and basic fallback) meta from raw HTML.
	 *
	 * @param string $html Raw page HTML.
	 * @param string $url  Original URL (used as fallback for og:url).
	 * @return array<string, string>
	 */
	private static function parse_og_tags( string $html, string $url ): array {
		$og = [
			'title'       => '',
			'description' => '',
			'image'       => '',
			'url'         => $url,
			'site_name'   => '',
		];

		$previous = libxml_use_internal_errors( true );
		$doc      = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_use_internal_errors( $previous );

		$metas = $doc->getElementsByTagName( 'meta' );

		foreach ( $metas as $meta ) {
			$property = $meta->getAttribute( 'property' );
			$content  = $meta->getAttribute( 'content' );

			if ( ! $property || ! $content ) {
				continue;
			}

			switch ( $property ) {
				case 'og:title':
					$og['title'] = $content;
					break;
				case 'og:description':
					$og['description'] = $content;
					break;
				case 'og:image':
					$og['image'] = $content;
					break;
				case 'og:url':
					$og['url'] = $content;
					break;
				case 'og:site_name':
					$og['site_name'] = $content;
					break;
			}
		}

		// Fallback to twitter:card meta.
		if ( empty( $og['title'] ) || empty( $og['description'] ) || empty( $og['image'] ) ) {
			foreach ( $metas as $meta ) {
				$name    = $meta->getAttribute( 'name' );
				$content = $meta->getAttribute( 'content' );

				if ( ! $name || ! $content ) {
					continue;
				}

				if ( empty( $og['title'] ) && 'twitter:title' === $name ) {
					$og['title'] = $content;
				}
				if ( empty( $og['description'] ) && 'twitter:description' === $name ) {
					$og['description'] = $content;
				}
				if ( empty( $og['image'] ) && 'twitter:image' === $name ) {
					$og['image'] = $content;
				}
			}
		}

		// Fallback to standard HTML tags.
		if ( empty( $og['title'] ) ) {
			$titles = $doc->getElementsByTagName( 'title' );
			if ( $titles->length > 0 ) {
				$og['title'] = trim( $titles->item( 0 )->textContent );
			}
		}

		if ( empty( $og['description'] ) ) {
			foreach ( $metas as $meta ) {
				if ( strtolower( $meta->getAttribute( 'name' ) ) === 'description' ) {
					$og['description'] = $meta->getAttribute( 'content' );
					break;
				}
			}
		}

		return array_map( 'sanitize_text_field', $og );
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ----------------------------------------------------------------*/

	/**
	 * Render an embed card.
	 *
	 * @param array<string, string> $og  Parsed OG data.
	 * @param string                $url The original embed URL.
	 * @return string HTML markup.
	 */
	public static function render_card( array $og, string $url ): string {
		$title       = esc_html( $og['title'] );
		$description = esc_html( $og['description'] );
		$link        = esc_url( ! empty( $og['url'] ) ? $og['url'] : $url );
		$site_name   = esc_html( $og['site_name'] );
		$host        = esc_html( wp_parse_url( $url, PHP_URL_HOST ) );

		$image_html = '';
		if ( ! empty( $og['image'] ) ) {
			$image_url  = esc_url( $og['image'] );
			$image_html = sprintf(
				'<div class="og-fallback-embed__image"><img src="%s" alt="%s" loading="lazy" /></div>',
				$image_url,
				$title
			);
		}

		$provider = ! empty( $site_name ) ? $site_name : $host;

		ob_start();
		?>
		<div class="og-fallback-embed">
			<?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="og-fallback-embed__content">
				<p class="og-fallback-embed__provider"><?php echo $provider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<p class="og-fallback-embed__title">
					<a href="<?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" target="_blank" rel="noopener noreferrer"><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				</p>
				<?php if ( $description ) : ?>
					<p class="og-fallback-embed__description"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------
	 * Front-end styles
	 * ----------------------------------------------------------------*/

	/**
	 * Enqueue card styles on the front end for the auto-embed fallback path.
	 *
	 * When the block is used, block.json's "style" field handles this
	 * automatically. But auto-embeds via embed_maybe_make_link can appear
	 * on pages that don't contain the block, so we enqueue the same file
	 * globally. WordPress deduplicates by handle.
	 */
	public static function enqueue_styles(): void {
		wp_enqueue_style(
			'og-fallback-embed-style',
			plugins_url( 'build/og-embed/style-index.css', __FILE__ ),
			[],
			filemtime( plugin_dir_path( __FILE__ ) . 'build/og-embed/style-index.css' )
		);
	}
}

add_action( 'plugins_loaded', [ 'OpenGraph_Fallback_Embed', 'register' ] );
add_action( 'wp_enqueue_scripts', [ 'OpenGraph_Fallback_Embed', 'enqueue_styles' ] );
add_action( 'rest_api_init', [ 'OpenGraph_Fallback_Embed', 'register_rest_route' ] );
