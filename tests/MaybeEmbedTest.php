<?php
/**
 * Tests for the auto-embed fallback.
 */

class MaybeEmbedTest extends WP_UnitTestCase {

	public function tear_down(): void {
		parent::tear_down();
		delete_transient( 'og_embed_' . md5( 'https://example.com/article' ) );
	}

	public function test_url_with_og_data_returns_card(): void {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '<html><head><meta property="og:title" content="Embed Title" /></head></html>',
				];
			}
		);

		$output = '<a href="https://example.com/article">https://example.com/article</a>';
		$result = OpenGraph_Fallback_Embed::maybe_embed( $output );

		$this->assertStringContainsString( 'og-fallback-embed', $result );
		$this->assertStringContainsString( 'Embed Title', $result );
	}

	public function test_url_without_og_data_returns_original(): void {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '<html><head></head><body></body></html>',
				];
			}
		);

		$output = '<a href="https://example.com/article">https://example.com/article</a>';
		$result = OpenGraph_Fallback_Embed::maybe_embed( $output );

		$this->assertSame( $output, $result );
	}

	public function test_no_url_in_output_returns_original(): void {
		$output = 'Just some plain text without URLs.';
		$result = OpenGraph_Fallback_Embed::maybe_embed( $output );

		$this->assertSame( $output, $result );
	}

	public function test_http_error_returns_original(): void {
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'Timeout' );
			}
		);

		$output = '<a href="https://example.com/article">https://example.com/article</a>';
		$result = OpenGraph_Fallback_Embed::maybe_embed( $output );

		$this->assertSame( $output, $result );
	}
}
