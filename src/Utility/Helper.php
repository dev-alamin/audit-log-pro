<?php
namespace Amin\AuditLogPro\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for utility functions.
 *
 * @since 1.0.0
 */
class Helper {

	/**
	 * Transient prefix.
	 */
	private const APP_PREFIX = 'adtlogpro_';

	/**
	 * Get app prefix.
	 *
	 * @return string
	 */
	public static function get_app_prefix(): string {
		return self::APP_PREFIX;
	}

	/**
	 * Set transient with prefix.
	 *
	 * @param string  $transient
	 * @param mixed   $value
	 * @param integer $expiration
	 * @return bool
	 */
	public static function set_transient( string $transient, mixed $value, $expiration = 0 ): bool {
		return set_transient( self::APP_PREFIX . $transient, $value, $expiration );
	}

	/**
	 * Get transient with prefix.
	 *
	 * @param string $transient
	 * @return bool
	 */
	public static function get_transient( string $transient ): bool {
		return get_transient( self::APP_PREFIX . $transient );
	}

	/**
	 * Delete transient with prefix.
	 *
	 * @param string $transient
	 * @return bool
	 */
	public function delete_transient( string $transient ): bool {
		return delete_transient( self::APP_PREFIX . $transient );
	}

	/**
	 * Set in-memory cache with prefix
	 *
	 * @param string  $key
	 * @param mixed   $value
	 * @param string  $group
	 * @param integer $expiration
	 * @return boolean
	 */
	public static function set_cache( string $key, mixed $value, string $group = 'adtlogpro', int $expiration = 0 ): bool {
		return wp_cache_set( self::APP_PREFIX . $key, $value, $group, $expiration );
	}

	/**
	 * Get user IP from server env
	 *
	 * @return string
	 */
	public static function get_user_ip(): string {
		$ip_adress = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip_adress = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip_adress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip_adress = $_SERVER['REMOTE_ADDR'];
		}

		return $ip_adress;
	}
}
