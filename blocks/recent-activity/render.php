<?php
/**
 * Server-side render callback for audit-log-pro/recent-activity.
 *
 * Dynamic block render files get $attributes, $content, $block injected
 * automatically by WP when referenced via block.json's "render" field.
 *
 * @package AuditLogPro
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( ALP_Capabilities::VIEW_LOG ) ) {
	return;
}

$number_of_items = isset( $attributes['numberOfItems'] ) ? absint( $attributes['numberOfItems'] ) : 5;
$event_type      = isset( $attributes['eventType'] ) ? sanitize_key( $attributes['eventType'] ) : '';

$rows = ALP_Query::get_log_page( 0, $number_of_items, $event_type );

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'alp-recent-activity' ) );
?>
<div <?php echo wp_kses_post( $wrapper_attributes ); ?>>
	<h3><?php esc_html_e( 'Recent Activity', 'audit-log-pro' ); ?></h3>
	<?php if ( empty( $rows ) ) : ?>
		<p><?php esc_html_e( 'No activity recorded yet.', 'audit-log-pro' ); ?></p>
	<?php else : ?>
		<ul class="alp-recent-activity__list">
			<?php foreach ( $rows as $row ) : ?>
				<li>
					<strong><?php echo esc_html( $row['event_type'] ); ?></strong>
					&mdash;
					<?php echo esc_html( $row['message'] ); ?>
					<time datetime="<?php echo esc_attr( $row['created_at'] ); ?>">
						<?php echo esc_html( human_time_diff( strtotime( $row['created_at'] ) ) . ' ago' ); ?>
					</time>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
