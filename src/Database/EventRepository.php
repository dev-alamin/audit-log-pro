<?php
namespace Amin\AuditLogPro\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger Repository
 * 
 * Plugin's event reposity for handling global
 * insertion, query for rest or anything, anywhere
 * 
 * @since 1.0.0
 * @author Al Amin <hmalaminmb4@gmail.com>
 * @package AuditLogPro
 */
class EventRepository {

	/**
	 * Insert Logs
	 *
	 * Plugin's main log inserter method
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function insert( array $data ): bool {

		if ( count( $data ) != 7 ) {
			return false; // We expect 7 items as per formatting
		}

		global $wpdb;
		$table = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;

		$inserted = $wpdb->insert(
			$table,
			$data,
			array(
				'%s',
				'%d',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( $inserted ) {
			wp_cache_delete( 'adtlogpro_dashboard_summary', 'adtlogpro' );
		}

		return (bool) $inserted;
	}

    /**
     * Find log by ID
     *
     * @param integer $id
     * @return object|null
     */
    public function find( int $id ) : ?object {
        global $wpdb;
		$table = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
        );
    }
}
