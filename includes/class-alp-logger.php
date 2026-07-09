<?php
/**
 * Write path. Kept separate from ALP_Query (read path) on purpose —
 * writes and reads have completely different performance profiles at
 * scale, and mixing them in one class makes it harder to reason about
 * which methods need to invalidate cache vs which methods read it.
 *
 * @package AuditLogPro
 */

defined( 'ABSPATH' ) || exit;

class ALP_Logger {

	public static function init() {
		// Example real-world hooks. In production you'd wire these to
		// whatever events the business actually cares about.
		add_action( 'wp_login', array( __CLASS__, 'log_login' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'log_order_status_change' ), 10, 4 );
		add_action( 'transition_post_status', array( __CLASS__, 'log_post_transition' ), 10, 3 );
	}

	/**
	 * Single write entry point. Every hook above funnels through this,
	 * so cache invalidation and the insert logic only live in one place.
	 */
	public static function log( $event_type, $object_type, $object_id, $message, $meta = array(), $user_id = null ) {
		global $wpdb;

		$table   = $wpdb->prefix . ALP_TABLE_NAME;
		$user_id = null !== $user_id ? $user_id : get_current_user_id();

		$wpdb->insert(
			$table,
			array(
				'event_type'  => sanitize_key( $event_type ),
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => absint( $object_id ),
				'user_id'     => absint( $user_id ),
				'message'     => wp_kses_post( $message ),
				'meta'        => wp_json_encode( $meta ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		// Invalidate the aggregate/dashboard cache — NOT the raw row cache,
		// because we don't cache individual rows on the write path. Only
		// the expensive computed views (counts, summaries) get cached,
		// and only those need busting here.
		wp_cache_delete( 'alp_dashboard_summary', 'alp' );
	}

	public static function log_login( $user_login, $user ) {
		self::log( 'user_login', 'user', $user->ID, sprintf( '%s logged in', $user_login ), array(), $user->ID );
	}

	public static function log_order_status_change( $order_id, $from, $to, $order ) {
		self::log(
			'order_status_change',
			'order',
			$order_id,
			sprintf( 'Order #%d moved from %s to %s', $order_id, $from, $to ),
			array( 'from' => $from, 'to' => $to )
		);
	}

	public static function log_post_transition( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status || 'revision' === $post->post_type ) {
			return;
		}
		self::log(
			'post_status_change',
			'post',
			$post->ID,
			sprintf( '"%s" moved from %s to %s', $post->post_title, $old_status, $new_status )
		);
	}
}
