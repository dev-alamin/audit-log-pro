<?php
namespace Amin\AuditLogPro\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\Registrable;
use WP_REST_Request;
use WP_REST_Server;
use WP_REST_Response;

class RestApi implements Registrable {

	private EventRepository $repository;

	/**
	 * Get logs namespace
	 *
	 * @since 1.0.0
	 */
	const NAMESPACE = 'adtlogpro/v1';

	public function __construct( EventRepository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'callback' ) );
	}

	public function callback() {
		register_rest_route(
			self::NAMESPACE,
			'/logs/',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => '__return_true', // MUST handle soon
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
