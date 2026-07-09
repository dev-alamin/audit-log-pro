<?php
/**
 * REST endpoints for external consumption (SIEM tools, reporting dashboards,
 * a separate analytics service, etc).
 *
 * @package AuditLogPro
 */

defined( 'ABSPATH' ) || exit;

class ALP_REST_API {

	const NAMESPACE = 'audit-log-pro/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_logs' ),
				'permission_callback' => array( __CLASS__, 'check_view_permission' ),
				'args'                => array(
					'after'      => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'per_page'   => array(
						'type'              => 'integer',
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'event_type' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/logs/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_summary' ),
				'permission_callback' => array( __CLASS__, 'check_view_permission' ),
			)
		);
	}

	/**
	 * Permission callback overhead matters at scale — this runs on every
	 * request. current_user_can() is cheap (checked against the already-
	 * loaded user object, no extra query), so we gate here rather than
	 * doing an expensive check inside the controller method.
	 */
	public static function check_view_permission() {
		return current_user_can( ALP_Capabilities::VIEW_LOG );
	}

	public static function get_logs( WP_REST_Request $request ) {
		$rows = ALP_Query::get_log_page(
			$request->get_param( 'after' ),
			$request->get_param( 'per_page' ),
			$request->get_param( 'event_type' )
		);

		$next_cursor = ! empty( $rows ) ? end( $rows )['id'] : null;

		return new WP_REST_Response(
			array(
				'data'        => $rows,
				'next_cursor' => $next_cursor,
			),
			200
		);
	}

	public static function get_summary( WP_REST_Request $request ) {
		return new WP_REST_Response( ALP_Query::get_dashboard_summary(), 200 );
	}
}
