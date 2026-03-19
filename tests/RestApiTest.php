<?php
/**
 * Tests for the REST API endpoint.
 */

class RestApiTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// Ensure REST server is initialized and routes are registered.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		parent::tear_down();

		global $wp_rest_server;
		$wp_rest_server = null;
	}

	public function test_unauthenticated_request_returns_401(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/og-fallback-embed/v1/preview' );
		$request->set_param( 'url', 'https://example.com' );
		$response = rest_do_request( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_user_without_edit_posts_returns_403(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/og-fallback-embed/v1/preview' );
		$request->set_param( 'url', 'https://example.com' );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_missing_url_param_returns_error(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/og-fallback-embed/v1/preview' );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_invalid_url_returns_error(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/og-fallback-embed/v1/preview' );
		$request->set_param( 'url', 'not-a-url' );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_valid_request_returns_og_data(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '<html><head><meta property="og:title" content="Preview Title" /></head></html>',
				];
			}
		);

		$request  = new WP_REST_Request( 'GET', '/og-fallback-embed/v1/preview' );
		$request->set_param( 'url', 'https://example.com/page' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Preview Title', $data['title'] );
	}
}
