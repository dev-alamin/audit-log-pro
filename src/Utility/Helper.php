<?php
namespace Amin\AuditLogPro\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_User;

class Helper {

	public static function get_user_ip( WP_User $user ): string {
		$ip_adress = '';

		if ( $user->ID !== 0 ) {
			// Check for proxy headers first, then fall back to standard remote address
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip_adress = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip_adress = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip_adress = $_SERVER['REMOTE_ADDR'];
			}
		}

		return $ip_adress;
	}
}
