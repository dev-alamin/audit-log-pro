<?php
namespace Amin\AuditLogPro\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Capabilities {

	/**
	 * All capabilities for AuditLogPro
	 *
	 * @since 1.0.0
	 */
	private const CAPS = array(
		'view'   => 'adtlogpro_view_log',
		'export' => 'adtlogpro_export_log',
		'purge'  => 'adtlogpro_purge_log',
	);

	/**
	 * Add Capabilities to required roles
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
			$editor->add_cap( self::view() ); // Only view log for editor.
		}
	}

	public static function view(): string {
		return self::CAPS['view'];
	}

	public static function export(): string {
		return self::CAPS['export'];
	}

	public static function purge(): string {
		return self::CAPS['purge'];
	}

	public static function remove_caps(): void {
		$roles = array(
			get_role( 'administrator' ),
			get_role( 'editor' ),
		);

		foreach ( $roles as $role ) {
			if ( ! $role ) {
				continue;
			}

			foreach ( self::CAPS as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
