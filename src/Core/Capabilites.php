<?php
namespace Amin\AuditLogPro\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Capabilites {

	/**
	 * All capabilities for AuditLogPro
	 *
	 * @since 1.0.0
	 */
	const CAPS = array(
		'view'   => 'adtlogpro_view_log',
		'export' => 'adtlogpro_export_log',
		'purge'  => 'adtlogpro_purge_log',
	);

	/**
	 * Add capabilites to required roles
	 *
	 * The method get called on activation hook on plugin.php
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_caps_on_activation() {
		$admin = get_role( 'administrator' );

		if ( $admin ) {
			foreach ( self::CAPS as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( self::CAPS['view'] ); // Only view log for editor
		}
	}
}
