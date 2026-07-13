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

	private const COLUMNS = array(
		'event_type'  => '%s',
		'user_id'     => '%d',
		'object_type' => '%s',
		'object_id'   => '%d',
		'ip_address'  => '%s',
		'message'     => '%s',
		'meta'        => '%s',
	);

	/**
	 * Insert a new activity log record securely.
	 *
	 * @param array $data Raw log data from the app loggers.
	 * @return bool True on successful creation, false otherwise.
	 */
	public function insert( array $data ): bool {
		if ( count( array_intersect_key( self::COLUMNS, $data ) ) !== count( self::COLUMNS ) ) {
			return false;
		}

		$cleaned_data = $this->sanitize_and_validate( $data );
		if ( ! $cleaned_data ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;

		$inserted = $wpdb->insert(
			$table,
			$cleaned_data,
			array_values( self::COLUMNS )
		);

		if ( $inserted ) {
			wp_cache_delete( 'adtlogpro_dashboard_summary', 'adtlogpro' );
		}

		return (bool) $inserted;
	}

	/**
	 * Keeps database pollution clean by running strict type checks and WP sanitization functions.
	 *
	 * @param array $data
	 * @return array|false Cleaned data array or false on system validation failures.
	 */
	private function sanitize_and_validate( array $data ): array|false {
		$ip = filter_var( $data['ip_address'], FILTER_VALIDATE_IP );

		return array(
			'event_type'  => sanitize_key( $data['event_type'] ),
			'user_id'     => absint( $data['user_id'] ),
			'object_type' => sanitize_key( $data['object_type'] ),
			'object_id'   => absint( $data['object_id'] ),
			'ip_address'  => $ip ? $ip : '0.0.0.0', // Fallback placeholder safely
			'message'     => wp_kses_post( $data['message'] ),
			'meta'        => is_array( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : $data['meta'],
		);
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
		);

		$args = wp_parse_args( $args, $default );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$values[] = $args['event_type'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		if ( ! empty( $args['cursor'] ) ) {
			$where[]  = 'cursor < %d';
			$values[] = $args['cursor'];
		}

		if ( ! empty( $args['object_type'] ) ) {
			$where[]  = 'object_type = %s';
			$values[] = $args['object_type'];
		}

		if ( ! empty( $args['object_id'] ) ) {
			$where[]  = 'object_id = %d';
			$values[] = $args['object_id'];
		}

		if ( ! empty( $args['created_at'] ) ) {
			$where[]  = 'created_at = %s';
			$values[] = $args['created_at'];
		}

		$sql      = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
		$values[] = $args['per_page'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}
}
