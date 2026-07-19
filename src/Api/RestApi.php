<?php
namespace Amin\AuditLogPro\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\RegistrationInterface;
use Amin\AuditLogPro\Core\Capabilities;
use Amin\AuditLogPro\Database\EventQuery;
use Amin\AuditLogPro\Utility\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WP_REST_Response;

/**
 * Plugin's main REST Api handler
 *
 * Responsible to register new route,
 * and their callbacks.
 *
 * @since 1.0.0
 * @author Al Amin <hmalaminmb4@gmail.com>
 * @package AuditLogPro
 */
class RestApi implements RegistrationInterface {

	/**
	 * Database container repository
	 *
	 * @var EventRepository
	 */
	private EventRepository $repository;

	/**
	 * Get logs namespace
	 *
	 * @since 1.0.0
	 */
	const NAMESPACE = 'adtlogpro/v1';

	/**
	 * Constructor for DI
	 *
	 * @param EventRepository $repository
	 */
	public function __construct( EventRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register, add required action
	 *
	 * @return void
	 */
	public function register_hook(): void {
		add_action( 'rest_api_init', array( $this, 'adtlogpro_rest_cb' ) );
	}

	/**
	 * API Callback
	 *
	 * @return void
	 */
	public function adtlogpro_rest_cb() {
		register_rest_route(
			self::NAMESPACE,
			'/logs/',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => function () {
					return array( Capabilities::class, 'can_view' );
				},
				'args'                => array(
					'per_page'       => array(
						'description'       => __( 'Number of logs per page', 'audit-log-pro' ),
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value > 0 && $value < 100;
						},
					),
					'cursor_id'      => array(
						'description'       => __( 'Return rows with id less than this cursor', 'audit-log-pro' ),
						'type'              => 'integer',
						'default'           => null,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return null === $value || ( is_numeric( $value ) && $value > 0 );
						},
					),
					'event_type'     => array(
						'description'       => __( 'Filter by event type', 'audit-log-pro' ),
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'object_type'    => array(
						'description'       => __( 'Filter by object type', 'audit-log-pro' ),
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'user_id'        => array(
						'description'       => __( 'Filter by acting user id', 'audit-log-pro' ),
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'object_id'      => array(
						'description'       => __( 'Filter by object id', 'audit-log-pro' ),
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'created_after'  => array(
						'description'       => __( 'Only events after this datetime (Y-m-d H:i:s)', 'audit-log-pro' ),
						'type'              => 'string',
						'default'           => null,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return null === $value || false !== strtotime( $value );
						},
					),
					'created_before' => array(
						'description'       => __( 'Only events before this datetime (Y-m-d H:i:s)', 'audit-log-pro' ),
						'type'              => 'string',
						'default'           => null,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return null === $value || false !== strtotime( $value );
						},
					),
				),
			)
		);
	}

	/**
	 * Get logs
	 *
	 * Get methods for getting data
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_logs( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$filters = new EventQuery(
			event_type    : $request['event_type'],
			actor_id      : $request['user_id'],
			object_type   : $request['object_type'],
			object_id     : $request['object_id'],
			created_after : $request['created_after'],
			created_before: $request['created_before'],
			cursor_id     : $request['cursor_id'],
			per_page      : $request['per_page'],
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key = Helper::get_app_prefix() . 'rest_get_logs' . md5( serialize( $filters ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		try {
			$logs = $this->repository->query( $filters );
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'adtlogpro_query_failed',
				__( 'Unable to retrie logs', 'audit-log-pro' ),
				array( 'status' => 500 )
			);
		}

		$arr = function ( $log ) {
				return array(
					'id'          => (int) $log->id,
					'event_type'  => $log->event_type,
					'user_id'     => (int) $log->user_id,
					'object_type' => $log->object_type,
					'object_id'   => (int) $log->object_id,
					'ip_address'  => $log->ip_address,
					'message'     => $log->message,
					'meta'        => json_decode( $log->meta, true ) ?? array(),
					'created_at'  => $log->created_at,
				);
		};

		$maped_data  = array_map( $arr, $logs );
		$next_cursor = ! empty( $logs ) ? end( $logs )->id : null;

		$data   = array(
			'data'        => $maped_data,
			'next_cursor' => $next_cursor,
			'has_more'    => count( $logs ) === $filters->per_page,
		);
		$cached = set_transient( $cache_key, $data, MINUTE_IN_SECONDS * 2 );

		return rest_ensure_response( $data );
	}
}
