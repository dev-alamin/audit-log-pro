<?php
// Path to WordPress core — wp-phpunit ships its own minimal core copy.
// define( 'ABSPATH', __DIR__ . '/vendor/wp-phpunit/wp-phpunit/' );
define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
// Test database — must be a real, empty, disposable DB.
define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' ); // your local MySQL root password, likely empty on a dev box
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WP_DEBUG', true );
