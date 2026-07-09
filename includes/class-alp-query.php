<?php
/**
 * Read path.
 *
 * Two things matter here at millions-of-rows scale:
 *
 * 1. Cursor pagination, not OFFSET. `LIMIT 50 OFFSET 200000` forces MySQL
 *    to scan and discard 200,000 rows before returning anything — it gets
 *    linearly slower the deeper you page. Keying off the last-seen id
 *    (WHERE id < :last_id ORDER BY id DESC LIMIT 50) uses the primary key
 *    index directly regardless of how deep you are.
 *
 * 2. Cache the aggregates, not the rows. Raw log rows change constantly
 *    (new events every second) so row-level caching would thrash. But a
 *    "counts per event_type over last 24h" dashboard summary is exactly
 *    the kind of expensive, repeatedly-read, infrequently-changing query
 *    that belongs behind wp_cache_get/set.
 *
 * @package AuditLogPro
 */

defined( 'ABSPATH' ) || exit;

class ALP_Query {

	const CACHE_GROUP = 'alp';
	const CACHE_TTL    = 5 * MINUTE_IN_SECONDS;

	/**
	 * Cursor-paginated fetch. $after_id = 0 gets the first page (most recent).
	 *
	 * @param int    $after_id   Last id seen by the client, 0 for first page.
	 * @param int    $per_page   Rows per page, capped to avoid abuse.
	 * @param string $event_type Optional filter.
	 */
	public static function get_log_page( $after_id = 0, $per_page = 50, $event_type = '' ) {
		global $wpdb;

		$table    = $wpdb->prefix . ALP_TABLE_NAME;
		$per_page = min( max( (int) $per_page, 1 ), 100 ); // hard ceiling, don't trust client input

		$where  = array( '1=1' );
		$params = array();

		if ( $after_id > 0 ) {
			$where[]  = 'id < %d';
			$params[] = $after_id;
		}

		if ( ! empty( $event_type ) ) {
			$where[]  = 'event_type = %s';
			$params[] = sanitize_key( $event_type );
		}

		$where_sql = implode( ' AND ', $where );
		$params[]  = $per_page;

		// Prepared statement even though $where_sql is built from our own
		// controlled fragments — the values (id, event_type, limit) still
		// go through %d/%s placeholders. Never string-interpolate user input.
		$sql = $wpdb->prepare(
			"SELECT id, event_type, object_type, object_id, user_id, message, meta, created_at
			 FROM {$table}
			 WHERE {$where_sql}
			 ORDER BY id DESC
			 LIMIT %d",
			$params
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Expensive aggregate query, cached. This is the one that would hurt
	 * on a dashboard hit on every admin page load if left uncached.
	 */
	public static function get_dashboard_summary() {
		$cached = wp_cache_get( 'alp_dashboard_summary', self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . ALP_TABLE_NAME;

		$results = $wpdb->get_results(
			"SELECT event_type, COUNT(*) as total
			 FROM {$table}
			 WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)
			 GROUP BY event_type
			 ORDER BY total DESC",
			ARRAY_A
		);

		wp_cache_set( 'alp_dashboard_summary', $results, self::CACHE_GROUP, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Total row count for a given cutoff — used by the cron purge job to
	 * report what it's about to delete before it does it.
	 */
	public static function count_older_than( $days ) {
		global $wpdb;
		$table = $wpdb->prefix . ALP_TABLE_NAME;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}
