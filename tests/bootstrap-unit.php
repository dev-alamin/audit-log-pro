<?php
/**
 * tests/bootstrap-unit.php
 *
 * Bootstrap for the FAST unit test suite (Brain Monkey mocked WP functions,
 * mocked EventRepository) — deliberately does NOT boot WordPress or touch
 * a database. If a unit test ever needs a real WP function that isn't
 * stubbed, it should fail loudly here rather than silently falling through
 * to a real WordPress environment.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Minimal WP_User test double.
 *
 * Real WP core is never loaded in this suite, so WP_User doesn't exist.
 * This stub gives Brain Monkey-mocked functions something real to return
 * that satisfies WPBridge's WP_User return type hints.
 */
if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public int $ID = 0;
		public string $user_login = '';

		public function __construct( int $id = 0, string $user_login = '' ) {
			$this->ID          = $id;
			$this->user_login  = $user_login;
		}

		public function exists(): bool {
			return $this->ID > 0;
		}
	}
}