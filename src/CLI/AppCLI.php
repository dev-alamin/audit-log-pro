<?php
namespace Amin\AuditLogPro\CLI;

use WP_CLI;
use Amin\AuditLogPro\Database\Event;
use Amin\AuditLogPro\Database\EventRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Log Pro CLI
 *
 * To run this, use: wp adtlog seed --count=50
 */
class AppCLI {

	/**
	 * Seeds dummy audit logs into the cstom table.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : Number of dummy logs to generate.
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *      wp adtlog seed --count=100
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments (flags like --count).
	 */
	public function seed( $args, $assoc_args ) {
		global $wpdb;

		// 1. Get & sanitize arguments
		$count      = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'count', 10 );
		$table      = $wpdb->prefix . ADTLOGPRO_TABLE_NAME; // Adjust to your actual constant/table name.
		$repository = new EventRepository();

		WP_CLI::log( sprintf( 'Starting generation of %d dummy logs...', $count ) );

		// Prep dummy data pools
		$event_type   = array( 'user_login', 'post_updated', 'plugin_activated', 'settings_changed' );
		$object_types = array( 'user', 'post', 'plugin', 'options' );
		$ips          = array( '192.168.1.1', '127.0.0.1', '8.8.8.8', '10.0.0.15' );
		$message      = array(
			'User logged in successfully.',
			'Post ID %d updated by Administrator.',
			'Plugin activated successfully.',
			'General settings modified.',
		);

		// We use WP_CLI's built-in progress bar for excellent UX.
		$progress = \WP_CLI\Utils\make_progress_bar( 'Inserting logs', $count );
		$inserted = 0;

		for ( $i = 0; $i < $count; $i++ ) {
			$event = new Event(
				$event_type[ array_rand( $event_type ) ],
				wp_rand( 1, 5 ),
				$object_types[ array_rand( $object_types ) ],
				wp_rand( 1, 200 ),
				$ips[ array_rand( $ips ) ],
				sprintf( $message[ array_rand( $message ) ], wp_rand( 1, 100 ) ),
				array(
					'user_agent' => 'WP-CLI Seeder Bot/1.0',
					'context'    => 'cli_testing',
				)
			);

			if ( $repository->insert( $event ) ) {
				++$inserted;
			}
			$progress->tick();
		}

		$progress->finish();

		// 3. Provide feedback to the terminal.
		if ( $inserted === $count ) {
			WP_CLI::success( sprintf( 'Successfully seeded %d logs into the database!', $inserted ) );
		} else {
			WP_CLI::warning( sprintf( 'Completed with issues. Inserted %d out of %d logs.', $inserted, $count ) );
		}
	}
}
