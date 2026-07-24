<?php
namespace Amin\AuditLogPro\Core;

use Amin\AuditLogPro\Database\EventRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Plugin's rewrite rules.
 *
 * @package AuditLogPro
 * @since 1.0.0
 */
class ReWrite {
	public function __construct() {
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_log_view' ) );
	}

	public function register_rewrite() {
		add_rewrite_rule( '^audit-log/view/([0-9]+)/?$', 'index.php?audit_log_view=1&audit_log_id=$matches[1]', 'top' );
	}

	public function register_query_vars( array $vars ): array {
		$vars[] = 'audit_log_view';
		$vars[] = 'audit_log_id';

		return $vars;
	}

	public function maybe_render_log_view() {
		if ( ! get_query_var( 'audit_log_view' ) ) {
			return;
		}

		$log_id = absint( get_query_var( 'audit_log_id' ) );

		if ( ! $log_id || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Invalid or unauthorized log request.', '', array( 'response' => 403 ) );
		}

		// Fetch via your EventQuery/EventRepository, not raw $wpdb here
		$event = ( new EventRepository() )->find( $log_id );

		if ( ! $event ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( '404' );
			exit;
		}

		// Render your own template, bypass theme entirely
		include ADTLOGPRO_PLUGIN_DIR . 'templates/single-log-view.php';
		exit;
	}
}
