<?php

namespace Amin\AuditLogPro\Tests\Unit;

use Amin\AuditLogPro\Database\Event;
use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\Loggers\UserLogger;
use Amin\AuditLogPro\Core\HookLoader;
use Amin\AuditLogPro\Services\WPBridge;
use PHPUnit\Framework\TestCase;

class UserLoggerTest extends TestCase {

	public function test_user_register_inserts_event(): void {

		$repository = $this->createMock( EventRepository::class );
		$wp         = $this->createMock( WPBridge::class );
		$loader     = $this->createMock( HookLoader::class );

		$user = (object) array(
			'ID'         => 5,
			'user_login' => 'amin',
		);

		$wp->method( 'get_userdata' )
			->with( 5 )
			->willReturn( $user );

		$wp->method( 'get_current_user_id' )
			->willReturn( 5 );

		$wp->method( 'get_user_ip' )
			->willReturn( '127.0.0.1' );

		$wp->method( 'actor_name' )
			->willReturn( 'amin' );

		$repository->expects( $this->once() )
			->method( 'insert' )
			->with(
				$this->isInstanceOf( Event::class )
			);

		$logger = new UserLogger(
			$repository,
			$wp,
			$loader
		);

		$logger->action_user_register( 5 );
	}
}