<?php
/**
 * Integration test bootstrap.
 *
 * Loads the real WordPress test environment via wp-phpunit so
 * WP_UnitTestCase, $wpdb, hooks, etc. are all genuinely available.
 */
require_once __DIR__ . '/../vendor/autoload.php';

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/../vendor/yoast/phpunit-polyfills' );
}

// Must be a PHP constant, not just an env var — wp-phpunit checks defined(), not getenv().
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/../wp-tests-config.php' );
}

$_tests_dir = getenv( 'WP_PHPUNIT__DIR' ) ?: __DIR__ . '/../vendor/wp-phpunit/wp-phpunit';

require_once "{$_tests_dir}/includes/functions.php";

function _adtlogpro_manually_load_plugin() {
	require dirname( __DIR__ ) . '/audit-log-pro.php';
}
tests_add_filter( 'muplugins_loaded', '_adtlogpro_manually_load_plugin' );

require "{$_tests_dir}/includes/bootstrap.php";