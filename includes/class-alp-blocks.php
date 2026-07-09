<?php
/**
 * Registers the "Recent Activity" dynamic block and the post-editor
 * SlotFill sidebar panel.
 *
 * @package AuditLogPro
 */

defined( 'ABSPATH' ) || exit;

class ALP_Blocks {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_sidebar' ) );
	}

	/**
	 * Dynamic block — server-rendered via render.php rather than saving
	 * static markup, because the content (recent log rows) changes on
	 * every page load and must reflect the current DB state, not whatever
	 * was in the DB when the editor last saved the post.
	 */
	public static function register_block() {
		register_block_type(
			ALP_PLUGIN_DIR . 'blocks/recent-activity'
		);
	}

	/**
	 * SlotFill panel injected into the post editor sidebar, showing edit
	 * history for the post currently open — pulled from our custom table
	 * via useSelect against the REST endpoint, not from postmeta.
	 */
	public static function enqueue_editor_sidebar() {
		$asset_file = ALP_PLUGIN_DIR . 'src/slotfill/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return; // not built yet — run npm run build
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'alp-slotfill',
			ALP_PLUGIN_URL . 'src/slotfill/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}
}
