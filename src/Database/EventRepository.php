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
	public function find( int $id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
		);
	}

	/**
	 * Query Events
	 *
	 * Plugin's main method to query event
	 * by various params
	 *
	 * @param array $args
	 * @return array
	 */
	public function query( array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;

		$default = array(
			'event_type'  => '',
			'user_id'     => 0,
			'object_type' => '',
			'object_id'   => 0,
			'after'       => '',
			'cursor'      => '',
			'per_page'    => 20,
			'page'        => 1,
		);

		$args = wp_parse_args( $default, $args );

		$where  = array( '1=1' );
		$values = array();

		if ( isset( $args['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$values[] = $args['event_type'];
		}

		if ( isset( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		if ( isset( $args['cursor'] ) ) {
			$where[]  = 'cursor < %d';
			$values[] = $args['cursor'];
		}

		if ( isset( $args['object_type'] ) ) {
			$where[]  = 'object_type = %s';
			$values[] = $args['object_type'];
		}

		if ( isset( $args['object_id'] ) ) {
			$where[]  = 'object_id = %d';
			$values[] = $args['object_id'];
		}

		if ( isset( $args['created_at'] ) ) {
			$where[]  = 'created_at = %s';
			$values[] = $args['created_at'];
		}

		$sql      = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
		$values[] = $args['per_page'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}
}
