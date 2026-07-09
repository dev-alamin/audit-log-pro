<?php
/**
 * Handles table creation on activation.
 *
 * Design decision: custom table instead of a Custom Post Type.
 * At millions of rows, wp_posts/wp_postmeta becomes a liability —
 * EAV-style postmeta means every filter is a JOIN, and CPT rows
 * carry post overhead (revisions, terms, meta cache priming) we
 * don't need for an append-mostly log. A dedicated table lets us
 * index exactly the columns we query on and skip WP's post machinery
 * entirely on the write path, which matters when writes are frequent.
 *
 * @package AuditLogPro
 */

defined( 'ABSPATH' ) || exit;

class ALP_Activator {

	public static function activate() {
		self::create_table();
		ALP_Cron::schedule_events();
		flush_rewrite_rules();
	}

	private static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . ALP_TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta is picky about formatting: two spaces after PRIMARY KEY,
		// each field on its own line, no trailing comma issues.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type VARCHAR(64) NOT NULL,
			object_type VARCHAR(64) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			message TEXT NOT NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY object_lookup (object_type, object_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'alp_db_version', ALP_VERSION );
	}
}
