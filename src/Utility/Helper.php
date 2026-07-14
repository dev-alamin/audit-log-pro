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
