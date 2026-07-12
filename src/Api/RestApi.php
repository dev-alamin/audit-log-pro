<?php
namespace Amin\AuditLogPro\Api;

use Amin\AuditLogPro\Registrable;
use WP_REST_Request;
use WP_REST_Server;
use WP_REST_Response;

class RestApi implements Registrable {

	/**
	 * Get logs namespace
	 *
	 * @since 1.0.0
	 */
	const NAMESPACE = 'adtlogpro/v1';

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
				'permission_callback' => '__return_true',
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
		return rest_ensure_response(
			array(
				'Hello from get logs method' . __FILE__ . __LINE__,
			)
		);
	}
}
