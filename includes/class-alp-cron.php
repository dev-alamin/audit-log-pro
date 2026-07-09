<?php
/**
 * WP-Cron jobs: nightly retention purge, daily digest email.
 *
 * Talking point for interview: wp-cron only fires on a page load, so on
 * a low-traffic staging site "daily" purge might not run for days, and on
 * a high-traffic production site many overlapping requests can each think
 * the cron is due and pile up. The correct production setup is
 * define('DISABLE_WP_CRON', true) in wp-config.php plus a real system
 * crontab entry hitting wp-cron.php on a fixed interval — that decouples
 * scheduling from traffic entirely. This class assumes that setup; it
 * doesn't try to work around wp-cron's own scheduling, it just registers
 * correctly so real cron can trigger it reliably.
 *
 * @package AuditLogPro
 */

defined( 'ABSPATH' ) || exit;

class ALP_Cron {

	const PURGE_HOOK  = 'alp_purge_old_logs';
	const DIGEST_HOOK = 'alp_send_daily_digest';
	const RETENTION_DAYS = 90;

	public static function init() {
		add_action( self::PURGE_HOOK, array( __CLASS__, 'purge_old_logs' ) );
		add_action( self::DIGEST_HOOK, array( __CLASS__, 'send_daily_digest' ) );
	}

	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::PURGE_HOOK );
		}
		if ( ! wp_next_scheduled( self::DIGEST_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::DIGEST_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::PURGE_HOOK );
		wp_clear_scheduled_hook( self::DIGEST_HOOK );
	}

	/**
	 * Deletes in batches rather than one DELETE across millions of rows —
	 * a single unbounded DELETE on a huge table can lock rows/gaps long
	 * enough to visibly stall other queries on the same table. Batching
	 * with a cap per run keeps each transaction short.
	 */
	public static function purge_old_logs() {
		global $wpdb;
		$table      = $wpdb->prefix . ALP_TABLE_NAME;
		$batch_size = 5000;

		do {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table}
					 WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
					 LIMIT %d",
					self::RETENTION_DAYS,
					$batch_size
				)
			);
		} while ( $deleted === $batch_size );

		wp_cache_delete( 'alp_dashboard_summary', 'alp' );
	}

	public static function send_daily_digest() {
		$summary = ALP_Query::get_dashboard_summary();

		if ( empty( $summary ) ) {
			return;
		}

		$lines = array();
		foreach ( $summary as $row ) {
			$lines[] = sprintf( '%s: %d', $row['event_type'], $row['total'] );
		}

		wp_mail(
			get_option( 'admin_email' ),
			'Audit Log Pro — Daily Digest',
			implode( "\n", $lines )
		);
	}
}
