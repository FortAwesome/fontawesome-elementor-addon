<?php
/**
 * Regression tests for the Compatibility::is_compatible_for_setup() gate.
 *
 * The gate starts from an *empty* WP_Error, so a single failed sub-check must
 * make it return a WP_Error (not true). Before the fix it used `> 1`, which
 * silently reported an incompatible environment as compatible whenever exactly
 * one check failed.
 *
 * @package FontAwesomeElementorAddon
 */

use FontAwesomeElementorAddon\Compatibility;

/**
 * @covers \FontAwesomeElementorAddon\Compatibility::is_compatible_for_setup
 */
class Test_Compatibility_Setup_Gate extends WP_UnitTestCase {

	/**
	 * Force the Font Awesome API compatibility HTTP request to a chosen outcome
	 * so that check is the only lever we vary. Every other setup check runs for
	 * real against the (healthy) wp-env test container and is expected to pass.
	 *
	 * @param bool $succeed Whether the mocked request should look successful.
	 */
	private function mock_fontawesome_api( bool $succeed ): void {
		add_filter(
			'pre_http_request',
			static function () use ( $succeed ) {
				if ( $succeed ) {
					return [
						'headers'  => [],
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode(
							[ 'data' => [ 'release' => [ 'version' => '7.0.0' ] ] ]
						),
					];
				}

				return new WP_Error( 'http_request_failed', 'Simulated network failure' );
			},
			10,
			0
		);
	}

	/**
	 * The core regression: exactly one failing check (the API) must yield a
	 * WP_Error, not a false "compatible" result.
	 */
	public function test_single_failed_check_returns_wp_error() {
		$this->mock_fontawesome_api( false );

		$result = Compatibility::is_compatible_for_setup();

		$this->assertNotTrue(
			$result,
			'A failed compatibility check must not report the environment as compatible.'
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains(
			'fontawesome_elementor_addon_compatibility_api_service',
			$result->get_error_codes()
		);
	}

	/**
	 * The complementary happy path: with the API reachable and every other
	 * check passing in the container, the gate returns true. This also proves
	 * the other checks pass, so the failure test above varies exactly one lever.
	 */
	public function test_all_checks_passing_returns_true() {
		$this->mock_fontawesome_api( true );

		$result = Compatibility::is_compatible_for_setup();

		if ( is_wp_error( $result ) ) {
			$this->fail(
				'Expected a compatible environment, got: '
					. implode( ' | ', $result->get_error_messages() )
			);
		}

		$this->assertTrue( $result );
	}
}
