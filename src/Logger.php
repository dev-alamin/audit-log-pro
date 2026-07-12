<?php
namespace Amin\AuditLogPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Amin\AuditLogPro\Registrable;
use Amin\AuditLogPro\Utility\Helper;
use Amin\AuditLogPro\Database\EventRepository;
use WP_User;

class Logger implements Registrable {

	private EventRepository $repository;

	public function __construct( EventRepository $repository ) {
		$this->repository = $repository;
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
		add_action( 'wp_login', array( $this, 'log_login' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'action_wp_logout' ) );
		add_action( 'delete_user', array( $this, 'action_delete_user' ) );
		add_action( 'profile_update', array( $this, 'action_profile_update' ), 10, 2 );
		add_action( 'user_register', array( $this, 'action_user_register' ), 10, 1 );
		add_action( 'after_password_reset', array( $this, 'action_after_password_reset' ), 10, 2 );
		add_action( 'set_user_role', array( $this, 'action_user_role_changed' ), 10, 3 );
	}

	/**
	 * Fires after a user's role has been changed.
	 *
	 * @param int    $user_id The ID of the user whose role has been changed.
	 * @param string $new_role The new role assigned to the user.
	 * @param array  $old_roles An array of the user's previous roles.
	 */
	public function action_user_role_changed( $user_id, $new_role, $old_roles ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;   // guard FIRST, before anything touches $user
		}

		$old_roles_str = implode( ', ', $old_roles );
		$message       = sprintf(
			'%s changed role from %s to %s',
			self::actor_name(),
			$old_roles_str,
			$new_role
		);

		if ( $user_id !== get_current_user_id() ) {
			$message = sprintf(
				'%s changed role of %s from %s to %s',
				self::actor_name(),
				$user->user_login,
				$old_roles_str,
				$new_role
			);
		}

		if ( $user ) {
			$this->log(
				'user_role_changed',
				get_current_user_id(),
				'user',
				$user->ID,
				Helper::get_user_ip(),
				$message,
				array()
			);
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
			$this->log(
				'user_password_reset',
				get_current_user_id(),
				'user',
				$user->ID,
				Helper::get_user_ip(),
				sprintf(
					'%s reset password',
					self::actor_name()
				),
				array()
			);
		}
	}

	public function action_user_register( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$this->log(
				'user_registered',
				get_current_user_id(),
				'user',
				$user->ID,
				Helper::get_user_ip(),
				sprintf( '%s registered', self::actor_name() ),
				array()
			);
		}
	}

	public function action_profile_update( int $user_id, $old_user_data ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;   // guard FIRST, before anything touches $user
		}
		$message = sprintf( '%s updated profile', self::actor_name() );

		if ( $user->ID !== get_current_user_id() ) {
			$message = sprintf( '%s updated profile of %s', self::actor_name(), $user->user_login );
		}

		if ( $user ) {
			$this->log(
				'user_profile_updated',
				get_current_user_id(),
				'user',
				$user->ID,
				Helper::get_user_ip(),
				$message,
				array()
			);
		}
	}

	public function action_delete_user( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$this->log(
				'user_deleted',
				get_current_user_id(),
				'user',
				$user->ID,
				Helper::get_user_ip(),
				sprintf( '%s deleted', self::actor_name() ),
				array()
			);
		}
	}

	/**
	 * Fires after a user is logged out.
	 *
	 * @param int $user_id ID of the user that was logged out.
	 */
	public function action_wp_logout( $user_id ): void {

		$user = get_userdata( $user_id );
		if ( $user ) {
			$this->log(
				'user_logout',
				$user->ID,
				'user',
				$user->ID,
				Helper::get_user_ip(),
				sprintf( '%s logged out', self::actor_name() ),
				array()
			);
		}
	}

	private function log(
		string $event_type,
		int $user_id,
		string $object_type,
		int $object_id,
		string $ip_adress,
		string $message,
		array $meta
	) {

		$data = array(
			'event_type'  => sanitize_key( $event_type ),
			'user_id'     => absint( $user_id ),
			'object_type' => sanitize_key( $object_type ),
			'object_id'   => absint( $object_id ),
			'ip_address'  => filter_var( $ip_adress, FILTER_VALIDATE_IP ),
			'message'     => wp_kses_post( $message ),
			'meta'        => wp_json_encode( $meta ),
		);

		$inserted = $this->repository->insert( $data );

		if ( ! $inserted ) {
			do_action( 'adtlogpro_log_insertion_failed', $data );
			error_log( 'Activity log cannot be inserted, please seek technical help' );
		}
	}

	public function log_login( string $username, WP_User $user ) {
		$this->log(
			'user_login',
			$user->ID,
			'user',
			$user->ID,
			Helper::get_user_ip(),
			sprintf( '%s logged in', $username ),
			array()
		);
	}

	private static function actor_name(): string {
		$current = wp_get_current_user();
		return $current->exists() ? $current->user_login : 'System';
	}
}
