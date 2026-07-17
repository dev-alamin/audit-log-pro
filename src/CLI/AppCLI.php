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
	 * Above this row count, batched raw inserts are used instead of
	 * EventRepository::insert() — one query per row becomes impractical
	 * at volume (round-trip overhead, SAVEQUERIES memory growth in debug
	 * environments, etc).
	 */
	private const BATCH_THRESHOLD = 1000;

	/**
	 * Row count above which a confirmation prompt is required, to guard
	 * against accidental huge seeds (e.g. a typo'd --count=1000000).
	 */
	private const CONFIRM_THRESHOLD = 10000;

	/**
	 * Seeds dummy audit logs into the custom table.
	 *
	 * For small counts, rows go through EventRepository::insert() — full
	 * sanitization, validation, and cache invalidation, same path real
	 * application code uses. For large counts, rows are inserted via
	 * batched raw SQL for performance; this deliberately bypasses
	 * EventRepository, since seed data doesn't need per-row validation
	 * and validating a million rows individually isn't worth the cost.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : Number of dummy logs to generate.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--batch-size=<number>]
	 * : Rows per batch insert when count exceeds the batching threshold.
	 * ---
	 * default: 500
	 * ---
	 *
	 * [--truncate]
	 * : Empty the table before seeding.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt for large seed counts.
	 *
	 * ## EXAMPLES
	 *
	 *      wp adtlog seed --count=100
	 *      wp adtlog seed --count=1000000 --batch-size=1000 --truncate --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments (flags like --count).
	 */
	public function seed( $args, $assoc_args ) {
		global $wpdb;

		$count      = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'count', 10 );
		$batch_size = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 500 );
		$truncate   = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'truncate', false );
		$table      = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;

		if ( $count < 1 ) {
			WP_CLI::error( 'Count must be a positive integer.' );
		}

		if ( $batch_size < 1 ) {
			WP_CLI::error( 'Batch size must be a positive integer.' );
		}

		if ( $count > self::CONFIRM_THRESHOLD ) {
			WP_CLI::confirm(
				sprintf( 'This will insert %d rows. Continue?', $count ),
				$assoc_args
			);
		}

		if ( $truncate ) {
			WP_CLI::log( 'Truncating existing table data...' );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name isn't user input, comes from a defined constant.
			$wpdb->prepare( 'TRUNCATE TABLE %s', $table );
		}

		WP_CLI::log( sprintf( 'Starting generation of %d dummy logs...', $count ) );

		$inserted = $count > self::BATCH_THRESHOLD
			? $this->seed_batched( $table, $count, $batch_size )
			: $this->seed_via_repository( $count );

		if ( $inserted === $count ) {
			WP_CLI::success( sprintf( 'Successfully seeded %d logs into the database!', $inserted ) );
		} else {
			WP_CLI::warning( sprintf( 'Completed with issues. Inserted %d out of %d logs.', $inserted, $count ) );
		}
	}

	/**
	 * Seeds rows one at a time through EventRepository::insert() — full
	 * sanitization/validation path, same as real application writes.
	 * Appropriate for small counts where per-row overhead doesn't matter.
	 *
	 * @param int $count Number of rows to insert.
	 * @return int Number of rows actually inserted.
	 */
	private function seed_via_repository( int $count ): int {
		$repository = new EventRepository();
		$progress   = \WP_CLI\Utils\make_progress_bar( 'Inserting logs', $count );
		$inserted   = 0;

		for ( $i = 0; $i < $count; $i++ ) {
			$event = new Event(
				$this->random_event_type(),
				wp_rand( 1, 5 ),
				$this->random_object_type(),
				wp_rand( 1, 200 ),
				$this->random_ip(),
				$this->random_message(),
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

		return $inserted;
	}

	/**
	 * Seeds rows via batched raw INSERT statements. Deliberately bypasses
	 * EventRepository for performance at volume — see class docblock note
	 * on seed() for the reasoning.
	 *
	 * @param string $table      Fully-prefixed table name.
	 * @param int    $count      Total rows to insert.
	 * @param int    $batch_size Rows per batch.
	 * @return int Number of rows actually inserted.
	 */
	private function seed_batched( string $table, int $count, int $batch_size ): int {
		global $wpdb;

		$progress  = \WP_CLI\Utils\make_progress_bar( 'Inserting logs', $count );
		$inserted  = 0;
		$remaining = $count;

		while ( $remaining > 0 ) {
			$current_batch = min( $batch_size, $remaining );
			$values        = array();
			$placeholders  = array();

			for ( $i = 0; $i < $current_batch; $i++ ) {
				$placeholders[] = '(%s, %d, %s, %d, %s, %s, %s)';

				array_push(
					$values,
					$this->random_event_type(),
					wp_rand( 1, 5 ),
					$this->random_object_type(),
					wp_rand( 1, 200 ),
					$this->random_ip(),
					$this->random_message(),
					wp_json_encode(
						array(
							'user_agent' => 'WP-CLI Seeder Bot/1.0',
							'context'    => 'cli_testing',
						)
					)
				);
			}

			$sql = "INSERT INTO {$table} "
				. '(event_type, user_id, object_type, object_id, ip_address, message, meta) VALUES '
				. implode( ', ', $placeholders );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built from a fixed template + %s/%d placeholders below, not raw user input.
			$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

			if ( false === $result ) {
				WP_CLI::warning(
					sprintf( 'Batch insert failed: %s', $wpdb->last_error )
				);
			} else {
				$inserted += $current_batch;
			}

			$remaining -= $current_batch;
			$progress->tick( $current_batch );
		}

		$progress->finish();

		return $inserted;
	}

	/**
	 * Create random event type.
	 *
	 * @return string
	 */
	private function random_event_type(): string {
		$types = array( 'user_login', 'post_updated', 'plugin_activated', 'settings_changed' );
		return $types[ array_rand( $types ) ];
	}

	/**
	 * Create random object type.
	 *
	 * @return string
	 */
	private function random_object_type(): string {
		$types = array( 'user', 'post', 'plugin', 'options' );
		return $types[ array_rand( $types ) ];
	}

	/**
	 * Create random user IP.
	 *
	 * @return string
	 */
	private function random_ip(): string {
		$ips = array( '192.168.1.1', '127.0.0.1', '8.8.8.8', '10.0.0.15' );
		return $ips[ array_rand( $ips ) ];
	}

	/**
	 * Create random message.
	 *
	 * @return string
	 */
	private function random_message(): string {
		$messages = array(
			'User logged in successfully.',
			'Post ID %d updated by Administrator.',
			'Plugin activated successfully.',
			'General settings modified.',
		);

		return sprintf( $messages[ array_rand( $messages ) ], wp_rand( 1, 100 ) );
	}
}
