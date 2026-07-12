<?php
namespace Amin\AuditLogPro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Amin\AuditLogPro\Registrable;
use Override;
use Amin\AuditLogPro\Database\EventRepository;

class Menu implements Registrable {
	#[Override]
	public function register(): void {
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
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Audit Log Pro</h1>
			<div id="adtlogpro-admin-root"></div>
		</div>
		<?php
	}
}
