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
				'method'              => WP_REST_Server::READABLE,
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
		$data = array();

		if ( isset( $request['per_page'] ) ) {
			$data['per_page'] = $request['per_page'];
		}

		return rest_ensure_response(
			$this->repository->query( $data ),
		);
	}
}
