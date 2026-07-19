<?php
// Path to WordPress core.
// In CI, install-wp-tests.sh downloads WordPress core and sets WP_CORE_DIR
// to point at it. Locally, no such env var exists, so we fall back to the
// assumption that this plugin lives inside a real WP install at
// wp-content/plugins/audit-log-pro/.
$wp_core_dir = getenv( 'WP_CORE_DIR' );

if ( false === $wp_core_dir || '' === $wp_core_dir ) {
	$wp_core_dir = dirname( __DIR__, 3 );
}

define( 'ABSPATH', rtrim( $wp_core_dir, '/' ) . '/' );

// Test database — must be a real, empty, disposable DB.
$db_name = getenv( 'WP_TESTS_DB_NAME' );
if ( false === $db_name || '' === $db_name ) {
	$db_name = 'wordpress_test';
}
define( 'DB_NAME', $db_name );

$db_user = getenv( 'WP_TESTS_DB_USER' );
if ( false === $db_user || '' === $db_user ) {
	$db_user = 'root';
}
define( 'DB_USER', $db_user );

$db_password = getenv( 'WP_TESTS_DB_PASSWORD' );
if ( false === $db_password ) {
	$db_password = ''; // your local MySQL root password, likely empty on a dev box
}
define( 'DB_PASSWORD', $db_password );

$db_host = getenv( 'WP_TESTS_DB_HOST' );
if ( false === $db_host || '' === $db_host ) {
	$db_host = 'localhost';
}
define( 'DB_HOST', $db_host );

define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WP_DEBUG', true );
