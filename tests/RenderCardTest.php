<?php
/**
 * Tests for card HTML rendering.
 */

class RenderCardTest extends WP_UnitTestCase {

	public function test_full_card(): void {
		$og = [
			'title'       => 'Test Title',
			'description' => 'Test Description',
			'image'       => 'https://example.com/image.jpg',
			'url'         => 'https://example.com/article',
			'site_name'   => 'Example Site',
		];

		$html = OpenGraph_Fallback_Embed::render_card( $og, 'https://example.com/article' );

		$this->assertStringContainsString( 'og-fallback-embed', $html );
		$this->assertStringContainsString( 'Test Title', $html );
		$this->assertStringContainsString( 'Test Description', $html );
		$this->assertStringContainsString( 'https://example.com/image.jpg', $html );
		$this->assertStringContainsString( 'Example Site', $html );
		$this->assertStringContainsString( 'og-fallback-embed__image', $html );
	}

	public function test_missing_image(): void {
		$og = [
			'title'       => 'Test Title',
			'description' => 'Test Description',
			'image'       => '',
			'url'         => 'https://example.com/article',
			'site_name'   => 'Example Site',
		];

		$html = OpenGraph_Fallback_Embed::render_card( $og, 'https://example.com/article' );

		$this->assertStringNotContainsString( 'og-fallback-embed__image', $html );
	}

	public function test_missing_description(): void {
		$og = [
			'title'       => 'Test Title',
			'description' => '',
			'image'       => '',
			'url'         => 'https://example.com/article',
			'site_name'   => '',
		];

		$html = OpenGraph_Fallback_Embed::render_card( $og, 'https://example.com/article' );

		$this->assertStringNotContainsString( 'og-fallback-embed__description', $html );
	}

	public function test_missing_site_name_falls_back_to_hostname(): void {
		$og = [
			'title'       => 'Test Title',
			'description' => '',
			'image'       => '',
			'url'         => 'https://example.com/article',
			'site_name'   => '',
		];

		$html = OpenGraph_Fallback_Embed::render_card( $og, 'https://example.com/article' );

		$this->assertStringContainsString( 'example.com', $html );
	}

	public function test_escaping(): void {
		$og = [
			'title'       => '<script>alert("xss")</script>',
			'description' => 'Desc with "quotes" & <tags>',
			'image'       => 'https://example.com/img.jpg?a=1&b=2',
			'url'         => 'https://example.com/article',
			'site_name'   => 'Site & "Name"',
		];

		$html = OpenGraph_Fallback_Embed::render_card( $og, 'https://example.com/article' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_link_has_noopener(): void {
		$og = [
			'title'       => 'Test',
			'description' => '',
			'image'       => '',
			'url'         => 'https://example.com',
			'site_name'   => '',
		];

		$html = OpenGraph_Fallback_Embed::render_card( $og, 'https://example.com' );

		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
		$this->assertStringContainsString( 'target="_blank"', $html );
	}
}
