<?php

// tests/bootstrap-unit.php — add before autoload or in a small stub file
require __DIR__ . '/../bootstrap-unit.php';

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public int $ID = 0;
		public string $user_login = '';

		public function __construct( int $id = 0, string $user_login = '' ) {
			$this->ID = $id;
			$this->user_login = $user_login;
		}

		public function exists(): bool {
			return $this->ID > 0;
		}
	}
}