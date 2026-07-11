<?php
namespace Amin\AuditLogPro;

use \Amin\AuditLogPro\Database\Schema;
class Plugin {
	public function __construct() {
		register_activation_hook( ADTLOGPRO_PLUGIN_FILE, array( Schema::class, 'create_table' ) );
        add_action( 'plugins_loaded', [ $this, 'maybe_upgrade_database' ] );
	}

    public function maybe_upgrade_database(){
        $installed = get_option( 'adtlogpro_db_version', '0.0.0' );

        if( version_compare( $installed, ADTLOGPRO_DB_VERSION, '<' ) ) {
            Schema::create_table();

            update_option( 'adtlogpro_db_version', ADTLOGPRO_DB_VERSION );
        }
    }
}
