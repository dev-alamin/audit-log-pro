<?php
namespace Tests\Integration\Database;

use Amin\AuditLogPro\Database\Event;
use Amin\AuditLogPro\Database\EventQuery;
use Amin\AuditLogPro\Database\EventRepository;
use WP_UnitTestCase;
/**
 * Integration tests for EventRepository.
 *
 * @package AuditLogPro
 */
class EventRepositoryTest extends WP_UnitTestCase {

	private EventRepository $repository;
	private string $table;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->table = $wpdb->prefix . ADTLOGPRO_TABLE_NAME;

		( new \Amin\AuditLogPro\Database\Schema() )::create_table();

		$this->repository = new EventRepository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->table}" );
		parent::tearDown();
	}

	private function make_event( array $overrides = array() ): Event {
		$defaults = array(
			'type'        => 'post_updated',
			'actor_id'    => 1,
			'object_type' => 'post',
			'object_id'   => 42,
			'ip'          => '127.0.0.1',
			'message'     => 'Post updated by admin',
			'meta'        => array( 'field' => 'title' ),
		);

		$data = array_merge( $defaults, $overrides );

		return new Event(
			$data['type'],
			$data['actor_id'],
			$data['object_type'],
			$data['object_id'],
			$data['ip'],
			$data['message'],
			$data['meta']
		);
	}

	public function test_insert_returns_true_on_success(): void {
		$event = $this->make_event();

		$this->assertTrue( $this->repository->insert( $event ) );
	}

	public function test_insert_persists_sanitized_data(): void {
		global $wpdb;

		$event = $this->make_event(
			array(
				'type'    => 'Post_Updated', // mixed case, should be sanitize_key()'d
				'message' => '<script>alert(1)</script>Clean text',
			)
		);

		$this->repository->insert( $event );

		$row = $wpdb->get_row( "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 1" );

		$this->assertSame( 'post_updated', $row->event_type );
		$this->assertStringNotContainsString( '<script>', $row->message );
	}

	public function test_insert_falls_back_to_placeholder_ip_when_invalid(): void {
		global $wpdb;

		$event = $this->make_event( array( 'ip' => 'not-an-ip' ) );
		$this->repository->insert( $event );

		$row = $wpdb->get_row( "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 1" );

		$this->assertSame( '0.0.0.0', $row->ip_address );
	}

	public function test_insert_clears_dashboard_summary_cache_on_success(): void {
		wp_cache_set( 'adtlogpro_dashboard_summary', array( 'stale' => true ), 'adtlogpro' );

		$this->repository->insert( $this->make_event() );

		$this->assertFalse( wp_cache_get( 'adtlogpro_dashboard_summary', 'adtlogpro' ) );
	}

	public function test_find_returns_matching_row(): void {
		global $wpdb;

		$this->repository->insert( $this->make_event( array( 'message' => 'findable event' ) ) );
		$id = (int) $wpdb->insert_id;

		$found = $this->repository->find( $id );

		$this->assertNotNull( $found );
		$this->assertSame( 'findable event', $found->message );
	}

	public function test_find_returns_null_for_missing_id(): void {
		$this->assertNull( $this->repository->find( 999999 ) );
	}

	public function test_query_filters_by_event_type(): void {
		$this->repository->insert( $this->make_event( array( 'type' => 'post_updated' ) ) );
		$this->repository->insert( $this->make_event( array( 'type' => 'user_deleted' ) ) );

		$results = $this->repository->query( new EventQuery( event_type: 'user_deleted' ) );

		$this->assertCount( 1, $results );
		$this->assertSame( 'user_deleted', $results[0]->event_type );
	}

	public function test_query_filters_by_actor_id(): void {
		$this->repository->insert( $this->make_event( array( 'actor_id' => 5 ) ) );
		$this->repository->insert( $this->make_event( array( 'actor_id' => 9 ) ) );

		$results = $this->repository->query( new EventQuery( actor_id: 5 ) );

		$this->assertCount( 1, $results );
		$this->assertSame( 5, (int) $results[0]->user_id );
	}

	public function test_query_respects_per_page(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->repository->insert( $this->make_event() );
		}

		$results = $this->repository->query( new EventQuery( per_page: 3 ) );

		$this->assertCount( 3, $results );
	}

	public function test_query_returns_empty_array_when_no_matches(): void {
		$this->repository->insert( $this->make_event( array( 'type' => 'post_updated' ) ) );

		$results = $this->repository->query( new EventQuery( event_type: 'nonexistent_type' ) );

		$this->assertSame( array(), $results );
	}

	public function test_query_cursor_id_excludes_rows_at_or_after_cursor(): void {
		global $wpdb;

		$this->repository->insert( $this->make_event() );
		$first_id = (int) $wpdb->insert_id;

		$this->repository->insert( $this->make_event() );
		$second_id = (int) $wpdb->insert_id;

		// cursor_id = second_id should only return rows with id < second_id.
		$results = $this->repository->query( new EventQuery( cursor_id: $second_id ) );

		$this->assertCount( 1, $results );
		$this->assertSame( $first_id, (int) $results[0]->id );
	}

	public function test_query_filters_by_created_after(): void {
		global $wpdb;

		$this->repository->insert( $this->make_event() );

		$results = $this->repository->query(
			new EventQuery( created_after: gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) )
		);

		$this->assertCount( 1, $results );
	}

	public function test_query_filters_by_created_before_excludes_future_window(): void {
		$this->repository->insert( $this->make_event() );

		$results = $this->repository->query(
			new EventQuery( created_before: gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) )
		);

		$this->assertSame( array(), $results );
	}
}