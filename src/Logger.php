<?php
namespace Amin\AuditLogPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Amin\AuditLogPro\Registrable;
use Amin\AuditLogPro\Utility\Helper;
use WP_User;

class Logger implements Registrable {

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

		$old_roles_str = implode( ', ', $old_roles );
		$message       = sprintf( '%s changed role from %s to %s', self::user_profile_updated_by( $user_id ), $old_roles_str, $new_role );

		if ( $user_id !== get_current_user_id() ) {
			$message = sprintf( '%s changed role of %s from %s to %s', self::user_profile_updated_by( $user_id ), $user->user_login, $old_roles_str, $new_role );
		}

		if ( $user ) {
			self::log( 'user_role_changed', $user->ID, 'user', $user->ID, Helper::get_user_ip( $user ), $message, array() );
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
			self::log( 'user_password_reset', $user->ID, 'user', $user->ID, Helper::get_user_ip( $user ), sprintf( '%s reset password', self::user_profile_updated_by( $user->ID ) ), array() );
		}
	}

	public function action_user_register( $user_id ): void {
		$user = get_userdata( $user_id );
		if ( $user ) {
			self::log( 'user_registered', $user->ID, 'user', $user->ID, Helper::get_user_ip( $user ), sprintf( '%s registered', self::user_profile_updated_by( $user_id ) ), array() );
		}
	}

	public function action_profile_update( $user_id, $old_user_data ): void {
		$user    = get_userdata( $user_id );
		$message = sprintf( '%s updated profile', self::user_profile_updated_by( $user->ID ) );

		if ( $user->ID !== get_current_user_id() ) {
			$message = sprintf( '%s updated profile of %s', self::user_profile_updated_by( $user->ID ), $user->user_login );
		}

		if ( $user ) {
			self::log( 'user_profile_updated', $user->ID, 'user', $user->ID, Helper::get_user_ip( $user ), $message, array() );
		}
	}

	public static function user_profile_updated_by( int $user_id ): string {
		$user       = get_userdata( $user_id );
		$changed_by = '';

		if ( $user === get_current_user_id() ) {
			$changed_by = self::user_profile_updated_by( $user_id );
		} else {
			$changed_by_user = get_userdata( get_current_user_id() );
			if ( $changed_by_user ) {
				$changed_by = $changed_by_user->user_login;
			}
		}

		return $changed_by;
	}

	public function action_delete_user( $user_id ): void {
		$user = get_userdata( $user_id );
		if ( $user ) {
			self::log( 'user_deleted', get_current_user_id(), 'user', $user->ID, Helper::get_user_ip( $user ), sprintf( '%s deleted', self::user_profile_updated_by( $user_id ) ), array() );
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
			self::log( 'user_logout', $user->ID, 'user', $user->ID, Helper::get_user_ip( $user ), sprintf( '%s logged out', self::user_profile_updated_by( $user_id ) ), array() );
		}
	}

	public static function log(
		string $event_type,
		int $user_id,
		string $object_type,
		int $object_id,
		string $ip_adress,
		string $message,
		array $meta
	) {
		global $wpdb;
		$table = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;

		$data = array(
			'event_type'  => sanitize_key( $event_type ),
			'user_id'     => absint( $user_id ),
			'object_type' => sanitize_key( $object_type ),
			'object_id'   => absint( $object_id ),
			'ip_address'  => filter_var( $ip_adress, FILTER_VALIDATE_IP ),
			'message'     => wp_kses_post( $message ),
			'meta'        => wp_json_encode( $meta ),
		);

		$format = array(
			'%s',
			'%d',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
		);

		$inserted = $wpdb->insert( $table, $data, $format );

		if ( $inserted ) {
			wp_cache_delete( 'adtlogpro_dashboard_summary', 'adtlogpro' );
		} else {
			error_log( __( 'AuditLogPro: log cannot be inserted. Please seek technical help or open support ticket', 'audit-log-pro' ) );
		}
	}

	public function log_login( string $username, WP_User $user ) {
		self::log( 'user_login', $user->ID, 'user', $user->ID, Helper::get_user_ip( $user ), sprintf( '%s logged in', $username ), array() );
	}
}
