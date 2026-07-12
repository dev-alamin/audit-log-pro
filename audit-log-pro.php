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

require __DIR__ . '/vendor/autoload.php';

define( 'ADTLOGPRO_VERSION', '0.1.0' );
define( 'ADTLOGPRO_DB_VERSION', '0.1.0' );
define( 'ADTLOGPRO_PLUGIN_FILE', __FILE__ );
define( 'ADTLOGPRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADTLOGPRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ADTLOGPRO_TABLE_NAME', 'adtlogpro_activity_log' ); // gets prefixed at runtime via $wpdb->prefix

new \Amin\AuditLogPro\Plugin();
