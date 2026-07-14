<?php
namespace Amin\AuditLogPro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Amin\AuditLogPro\RegistrationInterface;
use Override;

/**
 * Admin menu class
 *
 * Handles all the admin menu page registration.
 *
 * @since 1.0.0
 * @author Al amin <hmalaminmb@gmail.com>
 */
class Menu implements RegistrationInterface {

	#[Override]
	public function register_hook(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	public function admin_menu() {
		add_menu_page(
			__( 'Audit Log Pro', 'audit-log-pro' ),
			__( 'Audit Log Pro', 'audit-log-pro' ),
			'manage_options',
			'audit-log-pro',
			array( $this, 'menu_callback' )
		);
	}

	public function menu_callback() {
		$asset_file = ADTLOGPRO_PLUGIN_DIR . 'assets/js/build/admin.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			echo '<div class="wrap"><p>Run <code>npm run build</code> first.</p></div>';
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style(
			'adtlogpro-admin',
			ADTLOGPRO_PLUGIN_URL . 'assets/js/build/style-admin.css',
			array(),
			$asset['version']
		);

		wp_enqueue_script(
			'adtlogpro-admin',
			ADTLOGPRO_PLUGIN_URL . 'assets/js/build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		echo '<div class="wrap"><div id="adtlogpro-admin-root"></div></div>';
	}
}
