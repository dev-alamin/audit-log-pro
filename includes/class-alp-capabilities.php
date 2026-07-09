<?php
/**
 * RBAC layer.
 *
 * Deliberately NOT gated behind manage_options — audit logs are exactly
 * the kind of data you want a security/compliance role to see without
 * handing them full admin. We register a dedicated capability and map
 * it onto roles explicitly, so access is a policy decision, not a side
 * effect of being an administrator.
 *
 * @package AuditLogPro
 */

defined( 'ABSPATH' ) || exit;

class ALP_Capabilities {

	const VIEW_LOG   = 'alp_view_activity_log';
	const EXPORT_LOG = 'alp_export_activity_log';
	const PURGE_LOG   = 'alp_purge_activity_log';

	public static function init() {
		register_activation_hook( ALP_PLUGIN_FILE, array( __CLASS__, 'add_caps_on_activation' ) );
	}

	/**
	 * Grants view + export to admins and editors by default, purge only
	 * to admins. Sites can remap via the standard WP_Role API afterward —
	 * we don't hardcode role checks anywhere else in the plugin, every
	 * gate goes through current_user_can( self::VIEW_LOG ) etc.
	 */
	public static function add_caps_on_activation() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::VIEW_LOG );
			$admin->add_cap( self::EXPORT_LOG );
			$admin->add_cap( self::PURGE_LOG );
		}

		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( self::VIEW_LOG );
		}
	}
}
