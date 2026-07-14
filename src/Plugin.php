<?php
namespace Amin\AuditLogPro;

use Amin\AuditLogPro\Database\Schema;
use Amin\AuditLogPro\Api\RestApi;
use Amin\AuditLogPro\Loggers\UserLogger;
use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\Admin\Menu;
use Amin\AuditLogPro\Core\HookLoader;
use Amin\AuditLogPro\Services\WPBridge;
use Amin\AuditLogPro\Core\Capabilities;
use Amin\AuditLogPro\Loggers\OptionLogger;
use Amin\AuditLogPro\Loggers\PostLogger;
use Amin\AuditLogPro\Loggers\WooCommerceLogger;
use Amin\AuditLogPro\Registrable;

/**
 * Bootstraps the Audit Log Pro plugin.
 *
 * Registers activation hooks, performs database migrations, and initializes
 * all plugin modules.
 *
 * @package AuditLogPro
 * @since   1.0.0
 */
class Plugin {

	/**
	 * Constructor for Plugin class.
	 */
	public function __construct() {
		register_activation_hook( ADTLOGPRO_PLUGIN_FILE, array( Schema::class, 'create_table' ) );
		register_activation_hook( ADTLOGPRO_PLUGIN_FILE, array( Capabilities::class, 'add_caps_on_activation' ) );
		register_deactivation_hook( ADTLOGPRO_PLUGIN_FILE, array( Capabilities::class, 'remove_caps' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Updates the database schema when a newer version is available.
	 *
	 * Compares the installed database schema version with the current schema
	 * version and applies migrations when necessary.
	 *
	 * @return void
	 */
	private function maybe_upgrade_database() {
		$installed = get_option( 'adtlogpro_db_version', '0.0.0' );

		if ( version_compare( $installed, ADTLOGPRO_DB_VERSION, '<' ) ) {
			Schema::create_table();

			update_option( 'adtlogpro_db_version', ADTLOGPRO_DB_VERSION );
		}
	}

	/**
	 * Initializes all plugin modules.
	 *
	 * Executes database upgrades when required and registers the plugin's
	 * services and components.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( is_admin() ) {
			$this->maybe_upgrade_database();
			( new Menu() )->register_hook();
		}

		$loader     = new HookLoader();
		$wp         = new WPBridge();
		$repository = new EventRepository();

		/** @var LoggerInterface[] $modules */
		$modules = array(
			( new UserLogger( $repository, $wp, $loader ) ),
			( new PostLogger( $repository, $wp, $loader ) ),
			( new OptionLogger( $repository, $wp, $loader ) ),
			( new WooCommerceLogger( $repository, $wp, $loader ) ),
			( new RestApi( $repository ) ), // It has no DI deps.
		);

		foreach ( $modules as $module ) {
			$module->register_hook();
		}

		$loader->run();
	}
}
