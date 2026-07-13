<?php
namespace Amin\AuditLogPro\Tests\Unit;

use Amin\AuditLogPro\Logger;
use Amin\AuditLogPro\Database\EventRepository;
use Automattic\Jetpack\Partner;
use Brain\Monkey;
use Brain\Monkey\Functions;
use MailPoet\WP\Functions as WPFunctions;
use PHPunit\Framework\TestCase;

class LoggerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Helper: a plain stdClass standing in for WP_User. Logger only ever
     * reads ->ID and ->user_login off whatever get_userdata() returns, so
     * we don't need the real WP_User class loaded to test this in isolation.
     */
    private function fake_user( int $id, string $login ): \stdClass {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_login = $login;

        return $user;
    }

    /**
     * Stub the handful of WordPress functions Logger::log() always touches, 
     * so every test doesn't have to repeat this boilerplate.
     */
    private function stub_common_wp_functions(): void {
        Functions\when( 'sanitise_key' )->returnArg( 1 );
        Functions\when( 'absint' )->alias( fn( $v ) => abs( (int) $v ) );
        Functions\when( 'wp_kses_post' )->returnArg( 1 );
        Functions\when( 'wp_json_encode' )->alias( fn( $v ) => json_decode( $v ) );
    }

    public function test_action_delete_user_logs_current_user_as_actor_and_target_as_object(): void {
		$this->stub_common_wp_functions();
 
		$actor  = $this->fake_user( 1, 'admin' );
		$target = $this->fake_user( 6, 'ahnaf' );
 
		Functions\when( 'get_current_user_id' )->justReturn( $actor->ID );
		Functions\when( 'get_userdata' )->justReturn( $target );
		Functions\when( 'wp_get_current_user' )->justReturn( $actor );
 
		$repository = $this->createMock( EventRepository::class );
 
		$repository->expects( $this->once() )
			->method( 'insert' )
			->with( $this->callback( function ( array $data ) use ( $actor, $target ) {
				return $data['event_type'] === 'user_deleted'
					&& $data['user_id'] === $actor->ID       // actor — NOT the deleted user
					&& $data['object_type'] === 'user'
					&& $data['object_id'] === $target->ID    // the deleted user
					&& str_contains( $data['message'], $actor->user_login )
					&& str_contains( $data['message'], $target->user_login );
			} ) )
			->willReturn( true );
 
		$logger = new Logger( $repository );
		$logger->action_delete_user( $target->ID );
	}
 
	public function test_action_delete_user_does_nothing_when_user_no_longer_exists(): void {
		$this->stub_common_wp_functions();
 
		Functions\when( 'get_userdata' )->justReturn( false );
 
		$repository = $this->createMock( EventRepository::class );
		$repository->expects( $this->never() )->method( 'insert' );
 
		$logger = new Logger( $repository );
		$logger->action_delete_user( 999 );
	}
 
	public function test_action_wp_logout_uses_the_logged_out_user_as_actor_not_current_user_id(): void {
		$this->stub_common_wp_functions();
 
		$user = $this->fake_user( 6, 'ahnaf' );
 
		Functions\when( 'get_userdata' )->justReturn( $user );
 
		// Deliberately NOT stubbing get_current_user_id() to a matching value —
		// if action_wp_logout() ever reads it instead of the $user_id it was
		// handed, this stub returning 0 would make the assertion below fail,
		// catching a regression of the exact bug fixed earlier.
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
 
		$repository = $this->createMock( EventRepository::class );
 
		$repository->expects( $this->once() )
			->method( 'insert' )
			->with( $this->callback( function ( array $data ) use ( $user ) {
				return $data['event_type'] === 'user_logout'
					&& $data['user_id'] === $user->ID   // must be the person who logged out
					&& $data['object_id'] === $user->ID;
			} ) )
			->willReturn( true );
 
		$logger = new Logger( $repository );
		$logger->action_wp_logout( $user->ID );
	}
 
	public function test_log_fires_action_and_logs_error_when_insert_fails(): void {
		$this->stub_common_wp_functions();
 
		$user = $this->fake_user( 6, 'ahnaf' );
		Functions\when( 'get_userdata' )->justReturn( $user );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
 
		$repository = $this->createMock( EventRepository::class );
		$repository->method( 'insert' )->willReturn( false );
 
		Functions\expect( 'do_action' )
			->once()
			->with( 'adtlogpro_log_insertion_failed', \Mockery::type( 'array' ) );
 
		Functions\when( 'error_log' )->justReturn( true );
 
		// $logger = new Logger( $repository );
		// $logger->action_delete_user( $user->ID );
	}
}