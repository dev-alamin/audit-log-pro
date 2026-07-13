<?php
namespace Amin\AuditLogPro\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_User;
use Amin\AuditLogPro\Utility\Helper;

/**
 * Class WPBridge
 *
 * Acts as a proxy wrapper around WordPress global core functions
 * and static utilities to enable decoupled, isolated unit testing.
 *
 * @package Amin\AuditLogPro\Services
 * @since 1.0.0
 */
class WPBridge {

	/**
	 * Wrapper for get_userdata()
	 *
	 * @param int $user_id User ID.
	 * @return WP_User|false WP_User object on success, false on failure.
	 */
	public function get_userdata( int $user_id ) {
		return get_userdata( $user_id );
	}

	/**
	 * Wrapper for get_current_user_id()
	 *
	 * @return int Current user ID, or 0 if no user is logged in.
	 */
	public function get_current_user_id(): int {
		return get_current_user_id();
	}

	/**
	 * Wrapper for wp_get_current_user()
	 *
	 * @return WP_User Current user object.
	 */
	public function get_current_user(): WP_User {
		return wp_get_current_user();
	}

	/**
	 * Wrapper for static helper context.
	 * Moving this inside the bridge keeps the Logger clean of static side-effects.
	 *
	 * @return string The resolved client IP address.
	 */
	public function get_user_ip(): string {
		return Helper::get_user_ip();
	}

	public function actor_name(): string {
		$current = $this->get_current_user();
		return $current->exists() ? $current->user_login : 'System';
	}
}
