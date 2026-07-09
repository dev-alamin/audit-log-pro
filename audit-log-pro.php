<?php
/**
 * Plugin Name: Audit Log Pro
 * Description: Enterprise activity/audit logging for WordPress. Custom table storage,
 *              optimized $wpdb queries, object caching, REST API, WP-Cron retention,
 *              Gutenberg dynamic block + editor SlotFill, and RBAC-gated access.
 * Version:     0.1.0
 * Author:      Md Alamin
 * Text Domain: audit-log-pro
 *
 * @package AuditLogPro
 */

defined( 'ABSPATH' ) || exit;

define( 'ALP_VERSION', '0.1.0' );
define( 'ALP_PLUGIN_FILE', __FILE__ );
define( 'ALP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALP_TABLE_NAME', 'alp_activity_log' ); // gets prefixed at runtime via $wpdb->prefix

/**
 * Autoload our includes. No composer dependency needed for this scope,
 * a simple require map is enough and keeps the "why" visible in review.
 */
require_once ALP_PLUGIN_DIR . 'includes/class-alp-activator.php';
require_once ALP_PLUGIN_DIR . 'includes/class-alp-capabilities.php';
require_once ALP_PLUGIN_DIR . 'includes/class-alp-logger.php';
require_once ALP_PLUGIN_DIR . 'includes/class-alp-query.php';
require_once ALP_PLUGIN_DIR . 'includes/class-alp-rest-api.php';
require_once ALP_PLUGIN_DIR . 'includes/class-alp-cron.php';
require_once ALP_PLUGIN_DIR . 'includes/class-alp-blocks.php';

register_activation_hook( __FILE__, array( 'ALP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ALP_Cron', 'deactivate' ) );

/**
 * Bootstraps the plugin once all plugins are loaded, so we're guaranteed
 * WP_REST_Server, register_post_type(), etc. are available.
 */
function alp_bootstrap() {
	ALP_Capabilities::init();
	ALP_Logger::init();
	ALP_REST_API::init();
	ALP_Cron::init();
	ALP_Blocks::init();
}
add_action( 'plugins_loaded', 'alp_bootstrap' );