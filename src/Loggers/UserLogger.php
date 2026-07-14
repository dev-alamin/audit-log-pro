<?php
namespace Amin\AuditLogPro\Loggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Amin\AuditLogPro\Core\HookLoader;
use Amin\AuditLogPro\Loggers\LoggerInterface;
use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\Database\Event;
use Amin\AuditLogPro\Services\WPBridge;
use WP_User;

/**
 * Logs user-related activities.
 *
 * @since 1.0.0
 */
class UserLogger implements LoggerInterface {

	/**
	 * Event repository.
	 *
	 * @var EventRepository
	 */
	private EventRepository $repository;

	/**
	 * WPBridge for native WP functions.
	 *
	 * @var WPBridge
	 */
	private WPBridge $wp;

	/**
	 * Hook loader.
	 *
	 * @var HookLoader
	 */
	private HookLoader $loader;

	public function __construct( EventRepository $repository, WPBridge $wp, HookLoader $loader ) {
		$this->wp         = $wp;
		$this->repository = $repository;
		$this->loader     = $loader;
	}

	/**
	 * Register all loggable hooks
	 *
	 * Hooking all events for recording into databse.
	 * Inserting into DB happens there.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		$this->loader->add_action( 'wp_login', array( $this, 'log_login' ), 10, 2 );
		$this->loader->add_action( 'wp_logout', array( $this, 'action_wp_logout' ) );
		$this->loader->add_action( 'delete_user', array( $this, 'action_delete_user' ) );
		$this->loader->add_action( 'profile_update', array( $this, 'action_profile_update' ), 10, 2 );
		$this->loader->add_action( 'user_register', array( $this, 'action_user_register' ), 10, 1 );
		$this->loader->add_action( 'after_password_reset', array( $this, 'action_after_password_reset' ), 10, 2 );
		$this->loader->add_action( 'set_user_role', array( $this, 'action_user_role_changed' ), 10, 3 );
	}

	/**
	 * Fires after a user's role has been changed.
	 *
	 * @param int    $user_id The ID of the user whose role has been changed.
	 * @param string $new_role The new role assigned to the user.
	 * @param array  $old_roles An array of the user's previous roles.
	 */
	public function action_user_role_changed( $user_id, $new_role, $old_roles ) {
		$user = $this->wp->get_userdata( $user_id );

		if ( ! $user ) {
			return;   // guard FIRST, before anything touches $user
		}

		$old_roles_str = implode( ', ', $old_roles );
		$message       = sprintf(
			'%s changed role from %s to %s',
			$this->wp->actor_name(),
			$old_roles_str,
			$new_role
		);

		if ( $user_id !== $this->wp->get_current_user_id() ) {
			$message = sprintf(
				'%s changed role of %s from %s to %s',
				$this->wp->actor_name(),
				$user->user_login,
				$old_roles_str,
				$new_role
			);
		}

		$event = new Event(
			type       : 'user_role_changed',
			actor_id   : $this->wp->get_current_user_id(),
			object_type: 'user',
			object_id  : $user->ID,
			ip         : $this->wp->get_user_ip(),
			message    : $message,
			meta       : array(),
		);

		$inserted = $this->repository->insert( $event );
		if ( $inserted ) {
			error_log( 'User role changed: ' . $inserted );
		} else {
			error_log( 'User role cannot be changed' );
		}
	}

	/**
	 * Fires after a user has reset their password.
	 *
	 * @param WP_User $user The user object.
	 * @param string  $new_pass The new password.
	 */
	public function action_after_password_reset( $user, $new_pass ): void {
		if ( $user instanceof WP_User ) {
			$this->repository->insert(
				new Event(
					type       : 'user_password_reset',
					actor_id   : $this->wp->get_current_user_id(),
					object_type: 'user',
					object_id  : $user->ID,
					ip         : $this->wp->get_user_ip(),
					message    : sprintf( '%s reset password', $this->wp->actor_name() ),
					meta       : array(),
				)
			);
		}
	}

	public function action_user_register( int $user_id ): void {
		$user = $this->wp->get_userdata( $user_id );
		if ( $user ) {
			$this->repository->insert(
				new Event(
					type       : 'user_registered',
					actor_id   : $this->wp->get_current_user_id(),
					object_type: 'user',
					object_id  : $user->ID,
					ip         : $this->wp->get_user_ip(),
					message    : sprintf( '%s registered', $this->wp->actor_name() ),
					meta       : array(),
				)
			);
		}
	}

	public function action_profile_update( int $user_id, $old_user_data ): void {
		$user = $this->wp->get_userdata( $user_id );
		if ( ! $user ) {
			return;   // guard FIRST, before anything touches $user
		}
		$message = sprintf( '%s updated profile', $this->wp->actor_name() );

		if ( $user->ID !== $this->wp->get_current_user_id() ) {
			$message = sprintf( '%s updated profile of %s', $this->wp->actor_name(), $user->user_login );
		}

		if ( $user ) {
			$this->repository->insert(
				new Event(
					type       : 'user_profile_updated',
					actor_id   : $this->wp->get_current_user_id(),
					object_type: 'user',
					object_id  : $user->ID,
					ip         : $this->wp->get_user_ip(),
					message    : $message,
					meta       : array(),
				)
			);
		}
	}

	public function action_delete_user( int $user_id ): void {
		$user = $this->wp->get_userdata( $user_id );
		if ( $user ) {
			$this->repository->insert(
				new Event(
					type       : 'user_deleted',
					actor_id   : $this->wp->get_current_user_id(),
					object_type: 'user',
					object_id  : $user->ID,
					ip         : $this->wp->get_user_ip(),
					message    : sprintf( '%s deleted', $this->wp->actor_name() ),
					meta       : array(),
				)
			);
		}
	}

	/**
	 * Fires after a user is logged out.
	 *
	 * @param int $user_id ID of the user that was logged out.
	 */
	public function action_wp_logout( $user_id ): void {

		$user = $this->wp->get_userdata( $user_id );
		if ( $user ) {
			$this->repository->insert(
				new Event(
					type       : 'user_logout',
					actor_id   : $user->ID,
					object_type: 'user',
					object_id  : $user->ID,
					ip         : $this->wp->get_user_ip(),
					message    : sprintf( '%s logged out', $user->user_login ),
					meta       : array(),
				)
			);
		}
	}

	/**
	 * Logs a successful login.
	 *
	 * @param string  $username Username.
	 * @param WP_User $user     User object.
	 */
	public function log_login( string $username, WP_User $user ) {
		$this->repository->insert(
			new Event(
				type       : 'user_login',
				actor_id   : $user->ID,
				object_type: 'user',
				object_id  : $user->ID,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s logged in', $username ),
				meta       : array(),
			)
		);
	}
}
