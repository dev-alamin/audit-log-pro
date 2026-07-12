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
		add_action( 'wp_login', array( __CLASS__, 'log_login' ), 10, 2 );
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
			'ip_address'  => wp_kses_post( $ip_adress ),
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
			'%s',
		);

		$inserted = $wpdb->insert( $table, $data, $format );

		if ( $inserted ) {
			wp_cache_delete( 'adtlogpro_dashboard_summary', 'adtlogpro' );
		} else {
			error_log( __( 'AuditLogPro: log cannot be inserted. Please seek technical help or open support ticket', 'audit-log-pro' ) );
		}
	}

	public static function log_login( string $username, WP_User $user ) {

		self::log( 'user_login', $user->ID, 'user', $user->ID, Helper::get_user_ip( $user ), sprintf( '%s logged in', $username ), array() );
	}
}
