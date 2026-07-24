<?php
/**
 * Template: Single Audit Log View
 * Vars available: $event (Event object, passed in from caller)
 *
 * @package AuditLogPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$user = get_user_by( 'id', $event->user_id );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Audit Log Entry', 'audit-log-pro' ); ?></title>
</head>
<body>
	<h1><?php esc_html_e( 'Audit Log Entry', 'audit-log-pro' ); ?></h1>

	<table>
		<tr>
			<th><?php esc_html_e( 'ID', 'audit-log-pro' ); ?></th>
			<td><?php echo esc_html( $event->id ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Action', 'audit-log-pro' ); ?></th>
			<td><?php echo esc_html( $event->event_type ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Actor', 'audit-log-pro' ); ?></th>
			<td><?php echo esc_html( $user->user_login ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Timestamp', 'audit-log-pro' ); ?></th>
			<td><?php echo esc_html( $event->created_at ); ?></td>
		</tr>
	</table>
</body>
</html>