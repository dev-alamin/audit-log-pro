<?php
namespace Amin\AuditLogPro;

use Amin\AuditLogPro\Database\Schema;
use Amin\AuditLogPro\Api\RestApi;
class Plugin {
	public function __construct() {
		register_activation_hook( ADTLOGPRO_PLUGIN_FILE, array( Schema::class, 'create_table' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	private function maybe_upgrade_database() {
		$installed = get_option( 'adtlogpro_db_version', '0.0.0' );

		if ( version_compare( $installed, ADTLOGPRO_DB_VERSION, '<' ) ) {
			Schema::create_table();

			update_option( 'adtlogpro_db_version', ADTLOGPRO_DB_VERSION );
		}
	}

	public function boot(): void {
		if ( is_admin() ) {
			$this->maybe_upgrade_database();
		}

		/** @var Registrable[] $modules */
		$modules = array(
			( new RestApi() ),
		);

		foreach ( $modules as $module ) {
			$module->register();
		}
	}
}
