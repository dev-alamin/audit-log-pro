<?php
namespace Amin\AuditLogPro;

class Plugin {
	public function __construct() {
		register_activation_hook( ADTLOGPRO_PLUGIN_FILE, array( \Amin\AuditLogPro\ActivatePlugin::class, 'create_table' ) );
	}
}
