<?php
namespace Amin\AuditLogPro\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {

	/**
	 * Create Database Table
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;
		$table           = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$query = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(100) NOT NULL,
        user_id BIGINT UNSIGNED DEFAULT NULL,
        object_type VARCHAR(100) DEFAULT NULL,
        object_id BIGINT UNSIGNED DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        message TEXT DEFAULT NULL,
        meta LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY event_type (event_type),
        KEY user_id (user_id),
        KEY object_type (object_type),
        KEY object_id (object_id),
        KEY created_at (created_at)
        ) $charset_collate";

		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		dbDelta( $query );

        update_option( 'adtlogpro_db_version', ADTLOGPRO_DB_VERSION );
	}
}
