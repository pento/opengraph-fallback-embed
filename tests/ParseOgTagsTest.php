<?php
/**
 * Tests for OpenGraph tag parsing.
 */

class ParseOgTagsTest extends WP_UnitTestCase {

	public function tear_down(): void {
		parent::tear_down();
		delete_transient( 'og_embed_' . md5( 'https://example.com/article' ) );
	}

	/**
	 * Helper: mock wp_safe_remote_get to return given HTML.
	 */
	private function mock_http( string $html ): void {
		add_filter(
			'pre_http_request',
			function () use ( $html ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => $html,
				];
			}
		);
	}

	public function test_full_og_tags(): void {
		$this->mock_http(
			'<html><head>
				<meta property="og:title" content="Test Title" />
				<meta property="og:description" content="Test Description" />
				<meta property="og:image" content="https://example.com/image.jpg" />
				<meta property="og:url" content="https://example.com/canonical" />
				<meta property="og:site_name" content="Example Site" />
			</head><body></body></html>'
		);

		$data = OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		$this->assertSame( 'Test Title', $data['title'] );
		$this->assertSame( 'Test Description', $data['description'] );
		$this->assertSame( 'https://example.com/image.jpg', $data['image'] );
		$this->assertSame( 'https://example.com/canonical', $data['url'] );
		$this->assertSame( 'Example Site', $data['site_name'] );
	}

	public function test_twitter_card_fallback(): void {
		$this->mock_http(
			'<html><head>
				<meta name="twitter:title" content="Twitter Title" />
				<meta name="twitter:description" content="Twitter Desc" />
				<meta name="twitter:image" content="https://example.com/twitter.jpg" />
			</head><body></body></html>'
		);

		$data = OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		$this->assertSame( 'Twitter Title', $data['title'] );
		$this->assertSame( 'Twitter Desc', $data['description'] );
		$this->assertSame( 'https://example.com/twitter.jpg', $data['image'] );
	}

	public function test_html_title_and_meta_description_fallback(): void {
		$this->mock_http(
			'<html><head>
				<title>Page Title</title>
				<meta name="description" content="Meta description text" />
			</head><body></body></html>'
		);

		$data = OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		$this->assertSame( 'Page Title', $data['title'] );
		$this->assertSame( 'Meta description text', $data['description'] );
	}

	public function test_mixed_og_and_twitter(): void {
		$this->mock_http(
			'<html><head>
				<meta property="og:title" content="OG Title" />
				<meta name="twitter:description" content="Twitter Desc" />
				<meta name="twitter:image" content="https://example.com/twitter.jpg" />
			</head><body></body></html>'
		);

		$data = OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		$this->assertSame( 'OG Title', $data['title'] );
		$this->assertSame( 'Twitter Desc', $data['description'] );
		$this->assertSame( 'https://example.com/twitter.jpg', $data['image'] );
	}

	public function test_empty_html(): void {
		$this->mock_http( '' );

		$data = OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		$this->assertSame( '', $data['title'] );
		$this->assertSame( '', $data['description'] );
		$this->assertSame( '', $data['image'] );
	}

	public function test_special_characters_are_sanitized(): void {
		$this->mock_http(
			'<html><head>
				<meta property="og:title" content="Title &amp; &quot;Quotes&quot; &lt;Tags&gt;" />
				<meta property="og:description" content="Line1&#10;Line2" />
			</head><body></body></html>'
		);

		$data = OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		// sanitize_text_field strips tags and extra whitespace.
		$this->assertStringNotContainsString( "\n", $data['description'] );
		$this->assertNotEmpty( $data['title'] );
	}

	public function test_transient_caching(): void {
		$call_count = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$call_count ) {
				$call_count++;
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '<html><head><meta property="og:title" content="Cached" /></head></html>',
				];
			}
		);

		OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );
		OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		$this->assertSame( 1, $call_count, 'HTTP should only be called once due to caching.' );
	}

	public function test_http_error_returns_empty(): void {
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'Connection refused' );
			}
		);

		$data = OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		$this->assertSame( [], $data );
	}

	public function test_non_200_response_returns_empty(): void {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 404 ],
					'body'     => '<html><head><meta property="og:title" content="Not Found" /></head></html>',
				];
			}
		);

		$data = OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		$this->assertSame( [], $data );
	}

	public function test_url_preserved_when_og_url_absent(): void {
		$this->mock_http(
			'<html><head>
				<meta property="og:title" content="Title" />
			</head><body></body></html>'
		);

		$data = OpenGraph_Fallback_Embed::get_og_data( 'https://example.com/article' );

		$this->assertSame( 'https://example.com/article', $data['url'] );
	}
}
