<?php

namespace Tests\Unit\Services;

use Amin\AuditLogPro\Services\WPBridge;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class WPBridgeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_actor_name_returns_user_login_when_logged_in(): void {
		$user = new \WP_User( 5, 'alamin' );

		Functions\expect( 'wp_get_current_user' )
			->once()
			->andReturn( $user );

		$bridge = new WPBridge();

		$this->assertSame( 'alamin', $bridge->actor_name() );
	}

	public function test_actor_name_returns_system_when_logged_out(): void {
		$guest = new \WP_User( 0, '' );

		Functions\expect( 'wp_get_current_user' )
			->once()
			->andReturn( $guest );

		$bridge = new WPBridge();

		$this->assertSame( 'System', $bridge->actor_name() );
	}

	public function test_get_current_user_id_delegates_to_wp_function(): void {
		Functions\expect( 'get_current_user_id' )
			->once()
			->andReturn( 42 );

		$bridge = new WPBridge();

		$this->assertSame( 42, $bridge->get_current_user_id() );
	}

	public function test_get_userdata_delegates_to_wp_function(): void {
		$fake_user = new \WP_User( 7, 'someone' );

		Functions\expect( 'get_userdata' )
			->once()
			->with( 7 )
			->andReturn( $fake_user );

		$bridge = new WPBridge();

		$this->assertSame( $fake_user, $bridge->get_userdata( 7 ) );
	}

	public function test_get_order_returns_false_when_woocommerce_inactive(): void {
		// wc_get_order() is never defined in this test, simulating WC being inactive.
		$bridge = new WPBridge();

		$this->assertFalse( $bridge->get_order( 123 ) );
	}

	public function test_get_product_returns_false_when_woocommerce_inactive(): void {
		$bridge = new WPBridge();

		$this->assertFalse( $bridge->get_product( 456 ) );
	}
}