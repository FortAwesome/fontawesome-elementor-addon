<?php
/**
 * Tests for Setup_Kit error normalization, logging, and Throwable handling.
 *
 * @package FontAwesomeElementorAddon
 */

use FontAwesomeElementorAddon\Setup_Kit;

/**
 * @covers \FontAwesomeElementorAddon\Setup_Kit
 */
class Test_Setup_Kit_Errors extends WP_Ajax_UnitTestCase {

	/**
	 * Invoke a private static method on Setup_Kit.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Positional arguments.
	 * @return mixed
	 */
	private static function invoke( string $method, array $args = [] ) {
		$ref = new ReflectionMethod( Setup_Kit::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	/**
	 * Run $callback and return the JSON body that wp_send_json_* emitted.
	 *
	 * Under WP_Ajax_UnitTestCase, wp_die() throws instead of exiting, and its
	 * die handler consumes the open output buffer into $this->_last_response.
	 * We keep a buffer open for the handler to capture, then read the body from
	 * _last_response (resetting it first, since the harness appends to it).
	 *
	 * @param callable $callback Code that triggers a wp_send_json_* response.
	 * @return array
	 */
	private function capture_json( callable $callback ): array {
		$this->_last_response = '';
		$level                = ob_get_level();

		ob_start();

		try {
			$callback();
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_error() emitted a body, then wp_die().
		} catch ( WPAjaxDieStopException $e ) {
			// wp_die() with no captured body.
		}

		// Balance the buffer if wp_die's handler did not consume it.
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}

		return (array) json_decode( (string) $this->_last_response, true );
	}

	public function test_normalize_errors_wp_error_expands_all_codes_and_messages() {
		$error = new WP_Error( 'code_a', 'Message A' );
		$error->add( 'code_b', 'Message B' );

		$result = self::invoke( 'normalize_errors', [ $error ] );

		$this->assertSame(
			[
				[ 'code' => 'code_a', 'message' => 'Message A' ],
				[ 'code' => 'code_b', 'message' => 'Message B' ],
			],
			$result
		);
	}

	public function test_normalize_errors_string_payload() {
		$this->assertSame(
			[ [ 'code' => 'error', 'message' => 'Something broke' ] ],
			self::invoke( 'normalize_errors', [ 'Something broke' ] )
		);
	}

	public function test_normalize_errors_associative_array_with_message() {
		// This is the shape the front end used to silently drop.
		$this->assertSame(
			[ [ 'code' => 'error', 'message' => 'Plain array error' ] ],
			self::invoke( 'normalize_errors', [ [ 'message' => 'Plain array error' ] ] )
		);
	}

	public function test_normalize_errors_array_preserves_explicit_code() {
		$this->assertSame(
			[ [ 'code' => 'my_code', 'message' => 'With code' ] ],
			self::invoke( 'normalize_errors', [ [ 'code' => 'my_code', 'message' => 'With code' ] ] )
		);
	}

	public function test_normalize_errors_unrecognized_shape_falls_back_to_unknown() {
		$result = self::invoke( 'normalize_errors', [ [ 'unexpected' => true ] ] );

		$this->assertCount( 1, $result );
		$this->assertSame( 'unknown_error', $result[0]['code'] );
		$this->assertNotEmpty( $result[0]['message'] );
	}

	public function test_send_error_emits_normalized_list_as_data() {
		// Redirect the diagnostic log (this is a 500, so send_error writes one).
		$original = ini_get( 'error_log' );
		$log_file = tempnam( sys_get_temp_dir(), 'fa-elementor-log-' );
		ini_set( 'error_log', $log_file );

		try {
			$response = $this->capture_json(
				static function () {
					self::invoke( 'send_error', [ 'boom', 500 ] );
				}
			);
		} finally {
			ini_set( 'error_log', $original );
			@unlink( $log_file );
		}

		$this->assertFalse( $response['success'] );
		// data must be a *list* of { code, message } so the front end renders it.
		$this->assertSame( [ [ 'code' => 'error', 'message' => 'boom' ] ], $response['data'] );
	}

	public function test_send_error_logs_server_errors_but_not_client_errors() {
		$log_file = tempnam( sys_get_temp_dir(), 'fa-elementor-log-' );
		$original = ini_get( 'error_log' );
		ini_set( 'error_log', $log_file );

		try {
			// 4xx: expected and actionable by the caller, must NOT be logged.
			$this->capture_json(
				static function () {
					self::invoke( 'send_error', [ 'client side problem', 400 ] );
				}
			);
			$this->assertSame(
				'',
				trim( (string) file_get_contents( $log_file ) ),
				'Client (4xx) errors should not be written to the error log.'
			);

			// 5xx: must be logged so a diagnostic trail survives.
			$this->capture_json(
				static function () {
					self::invoke( 'send_error', [ 'server side problem', 500 ] );
				}
			);
			$this->assertStringContainsString(
				'server side problem',
				(string) file_get_contents( $log_file ),
				'Server (5xx) errors should be written to the error log.'
			);
		} finally {
			ini_set( 'error_log', $original );
			@unlink( $log_file );
		}
	}

	public function test_send_throwable_rethrows_ajax_die_exceptions() {
		// wp_send_json_* under the ajax harness must be allowed to unwind.
		$this->expectException( WPAjaxDieContinueException::class );
		self::invoke( 'send_throwable', [ new WPAjaxDieContinueException( 'continue' ) ] );
	}

	public function test_send_throwable_converts_regular_exception_to_normalized_500() {
		$log_file = tempnam( sys_get_temp_dir(), 'fa-elementor-log-' );
		$original = ini_get( 'error_log' );
		ini_set( 'error_log', $log_file );

		try {
			$response = $this->capture_json(
				static function () {
					self::invoke( 'send_throwable', [ new RuntimeException( 'kaboom' ) ] );
				}
			);
		} finally {
			ini_set( 'error_log', $original );
		}

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'error', $response['data'][0]['code'] );
		$this->assertNotEmpty( $response['data'][0]['message'] );
		// The uncaught throwable must leave a server-side trail with its class name.
		$this->assertStringContainsString(
			'RuntimeException',
			(string) file_get_contents( $log_file )
		);
		@unlink( $log_file );
	}
}
