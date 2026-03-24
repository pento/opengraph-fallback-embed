<?php
/**
 * Tests for the Embed block fallback paths.
 */

class EmbedBlockFallbackTest extends WP_UnitTestCase {

	/**
	 * URLs used across tests.
	 */
	private const TEST_URL    = 'https://example.com/article';
	private const YOUTUBE_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

	/**
	 * Minimal HTML with OG tags.
	 */
	private const OG_HTML = '<html><head>'
		. '<meta property="og:title" content="Test Title" />'
		. '<meta property="og:description" content="Test description" />'
		. '<meta property="og:site_name" content="Example Site" />'
		. '</head><body></body></html>';

	/**
	 * HTML without any OG tags.
	 */
	private const NO_OG_HTML = '<html><head></head><body></body></html>';

	public function tear_down(): void {
		parent::tear_down();
		delete_transient( OpenGraph_Fallback_Embed::CACHE_KEY_PREFIX . md5( self::TEST_URL ) );
		delete_transient( OpenGraph_Fallback_Embed::CACHE_KEY_PREFIX . md5( self::YOUTUBE_URL ) );
	}

	/* ------------------------------------------------------------------
	 * Helper: build a WP_REST_Request for the oEmbed proxy route.
	 * ----------------------------------------------------------------*/

	/**
	 * Create a mock REST request pointing at the oEmbed proxy route.
	 *
	 * @param string $url The URL parameter.
	 * @return WP_REST_Request
	 */
	private function make_proxy_request( string $url ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', $url );
		return $request;
	}

	/**
	 * Mock HTTP responses to return the given HTML body.
	 *
	 * @param string $body HTML body.
	 */
	private function mock_http_response( string $body ): void {
		add_filter(
			'pre_http_request',
			function () use ( $body ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => $body,
				];
			}
		);
	}

	/* ==================================================================
	 * Editor path: intercept_oembed_proxy
	 * ================================================================*/

	public function test_proxy_error_with_og_data_returns_oembed_object(): void {
		$this->mock_http_response( self::OG_HTML );

		$request  = $this->make_proxy_request( self::TEST_URL );
		$response = new WP_Error( 'oembed_invalid_url', 'Not found' );

		$result = OpenGraph_Fallback_Embed::intercept_oembed_proxy( $response, [], $request );

		$this->assertIsObject( $result );
		$this->assertSame( 'rich', $result->type );
		$this->assertSame( '1.0', $result->version );
		$this->assertStringContainsString( 'og-fallback-embed', $result->html );
		$this->assertStringContainsString( 'Test Title', $result->html );
		$this->assertSame( 'Example Site', $result->provider_name );
		$this->assertSame( 600, $result->width );
	}

	public function test_proxy_unknown_sentinel_with_og_data_returns_oembed_object(): void {
		$this->mock_http_response( self::OG_HTML );

		$request  = $this->make_proxy_request( self::TEST_URL );
		$response = '{{unknown}}';

		$result = OpenGraph_Fallback_Embed::intercept_oembed_proxy( $response, [], $request );

		$this->assertIsObject( $result );
		$this->assertSame( 'rich', $result->type );
		$this->assertStringContainsString( 'og-fallback-embed', $result->html );
		$this->assertStringContainsString( 'Test Title', $result->html );
	}

	public function test_proxy_success_not_intercepted(): void {
		$oembed_data = (object) [
			'version'       => '1.0',
			'type'          => 'video',
			'provider_name' => 'YouTube',
			'html'          => '<iframe src="https://www.youtube.com/embed/abc"></iframe>',
		];

		$request = $this->make_proxy_request( self::YOUTUBE_URL );
		$result  = OpenGraph_Fallback_Embed::intercept_oembed_proxy( $oembed_data, [], $request );

		$this->assertSame( $oembed_data, $result );
	}

	public function test_proxy_non_oembed_route_not_intercepted(): void {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'url', self::TEST_URL );

		$response = new WP_Error( 'rest_not_found', 'Not found' );
		$result   = OpenGraph_Fallback_Embed::intercept_oembed_proxy( $response, [], $request );

		$this->assertWPError( $result );
	}

	public function test_proxy_error_without_og_data_returns_original_error(): void {
		$this->mock_http_response( self::NO_OG_HTML );

		$request  = $this->make_proxy_request( self::TEST_URL );
		$response = new WP_Error( 'oembed_invalid_url', 'Not found' );

		$result = OpenGraph_Fallback_Embed::intercept_oembed_proxy( $response, [], $request );

		$this->assertWPError( $result );
	}

	public function test_proxy_response_includes_inline_styles(): void {
		$this->mock_http_response( self::OG_HTML );

		$request  = $this->make_proxy_request( self::TEST_URL );
		$response = new WP_Error( 'oembed_invalid_url', 'Not found' );

		$result = OpenGraph_Fallback_Embed::intercept_oembed_proxy( $response, [], $request );

		$this->assertIsObject( $result );
		$this->assertStringContainsString( '<style>', $result->html );
		$this->assertStringContainsString( 'og-fallback-embed', $result->html );
	}

	/* ==================================================================
	 * Frontend path: intercept_pre_oembed
	 * ================================================================*/

	public function test_pre_oembed_no_provider_returns_card(): void {
		$this->mock_http_response( self::OG_HTML );

		$result = OpenGraph_Fallback_Embed::intercept_pre_oembed( null, self::TEST_URL );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'og-fallback-embed', $result );
		$this->assertStringContainsString( 'Test Title', $result );
	}

	public function test_pre_oembed_with_provider_returns_null(): void {
		$result = OpenGraph_Fallback_Embed::intercept_pre_oembed( null, self::YOUTUBE_URL );

		$this->assertNull( $result );
	}

	public function test_pre_oembed_existing_result_not_overridden(): void {
		$existing = '<div class="existing-embed">Already handled</div>';

		$result = OpenGraph_Fallback_Embed::intercept_pre_oembed( $existing, self::TEST_URL );

		$this->assertSame( $existing, $result );
	}

	public function test_pre_oembed_no_og_data_returns_null(): void {
		$this->mock_http_response( self::NO_OG_HTML );

		$result = OpenGraph_Fallback_Embed::intercept_pre_oembed( null, self::TEST_URL );

		$this->assertNull( $result );
	}
}
