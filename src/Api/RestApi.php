<?php
namespace Amin\AuditLogPro\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\RegistrationInterface;
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
					return current_user_can( 'manage_options' );
				}, // MUST handle soon.
				'args'                => array(
					'per_page' => array(
						'description'       => __( 'Number of logs per page', 'audit-log-pro' ),
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value > 0 && $value < 100;
						},
					),
					'page'     => array(
						'description'       => __( 'Page number', 'audit-log-pro' ),
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
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
		$args = array_filter(
			array(
				'per_page' => $request['per_page'],
				'page'     => $request['page'],
			)
		);

		$logs = $this->repository->query( $args );

		$data = array_map(
			function ( $log ) {
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
			},
			$logs
		);

		return rest_ensure_response( $data );
	}
}
