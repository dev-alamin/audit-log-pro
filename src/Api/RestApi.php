<?php
namespace Amin\AuditLogPro\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\RegistrationInterface;
use Amin\AuditLogPro\Core\Capabilities;
use Amin\AuditLogPro\Database\Event;
use Amin\AuditLogPro\Database\EventQuery;
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
					'page'           => array(
						'description'       => __( 'Page number', 'audit-log-pro' ),
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'created_after'  => array(
						'description'       => __( 'Only events after this date (Y-m-d H:i:s)', 'audit-log-pro' ),
						'type'              => 'string',
						'default'           => null,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return null === $value || false !== strtotime( $value );
						},
					),
					'created_before' => array(
						'description'       => __( 'Only events before this date (Y-m-d H:i:s)', 'audit-log-pro' ),
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
	public function get_logs( WP_REST_Request $request ): WP_REST_Response {

		$event_type     = $request['event_type'] ? sanitize_key( $request['event_type'] ) : '';
		$object_type    = $request['object_type'] ? sanitize_key( $request['object_type'] ) : '';
		$actor_id       = $request['user_id'] ? absint( $request['user_id'] ) : 0;
		$object_id      = $request['object_id'] ? absint( $request['object_id'] ) : 0;
		$user_ip        = $request['user_ip'] ? filter_var( $request['user_ip'], FILTER_VALIDATE_IP ) : '0.0.0.0';
		$created_after  = $request['created_after'] ? $request['created_after'] : null;
		$created_before = $request['created_before'] ? $request['created_before'] : null;
		$cursor_id      = null;
		$per_page       = $request['per_page'] ? absint( $request['per_page'] ) : 10;

		$args = new EventQuery(
			event_type    : $event_type,
			actor_id      : $actor_id,
			object_type   : $object_type,
			object_id     : $object_id,
			created_after : $created_after,
			created_before: $created_before,
			cursor_id     : $cursor_id,
			per_page      : $per_page
		);

		$logs = $this->repository->query( $args );

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

		$data = array_map( $arr, $logs );

		return rest_ensure_response( $data );
	}
}
