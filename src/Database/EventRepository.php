<?php
namespace Amin\AuditLogPro\Database;

use Amin\AuditLogPro\Database\Event;

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
	 * All possible DB key
	 */
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
	 * @param Event $event Raw log data from the app loggers.
	 * @return bool True on successful creation, false otherwise.
	 */
	public function insert( Event $event ): bool {

		$cleaned_data = $this->sanitize_and_validate( $event );
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
	 * @param Event $event
	 * @return array Cleaned data array.
	 */
	private function sanitize_and_validate( Event $event ): array {
		$ip = filter_var( $event->ip, FILTER_VALIDATE_IP );

		return array(
			'event_type'  => sanitize_key( $event->type ),
			'user_id'     => absint( $event->actor_id ),
			'object_type' => sanitize_key( $event->object_type ),
			'object_id'   => absint( $event->object_id ),
			'ip_address'  => $ip ? $ip : '0.0.0.0', // Fallback placeholder safely
			'message'     => wp_kses_post( $event->message ),
			'meta'        => wp_json_encode( $event->meta ),
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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
		);
		// phpcs:enable
	}

	/**
	 * Query Events
	 *
	 * Plugin's main method to query event
	 * by various params
	 *
	 * @param EventQuery $filters
	 * @return array
	 */
	public function query( EventQuery $filters ): array {
		global $wpdb;
		$table = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;

		$where  = array( '1=1' );
		$values = array();

		if ( '' !== $filters->event_type ) {
			$where[]  = 'event_type = %s';
			$values[] = $filters->event_type;
		}

		if ( 0 !== $filters->actor_id ) {
			$where[]  = 'user_id = %d';
			$values[] = $filters->actor_id;
		}

		if ( '' !== $filters->object_type ) {
			$where[]  = 'object_type = %s';
			$values[] = $filters->object_type;
		}

		if ( 0 !== $filters->object_id ) {
			$where[]  = 'object_id = %d';
			$values[] = $filters->object_id;
		}

		if ( null !== $filters->created_after ) {
			$where[]  = 'created_at >= %s';
			$values[] = $filters->created_after;
		}

		if ( null !== $filters->created_before ) {
			$where[]  = 'created_at <= %s';
			$values[] = $filters->created_before;
		}

		if ( null !== $filters->cursor_id ) {
			$where[]  = 'id < %d';
			$values[] = $filters->cursor_id;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$values[] = $filters->per_page;
		$sql      = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';

		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$values ) );
		// phpcs:enable
	}
}
