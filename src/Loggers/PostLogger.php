<?php
namespace Amin\AuditLogPro\Loggers;

use Amin\AuditLogPro\Core\HookLoader;
use Amin\AuditLogPro\Registrable;
use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\Database\Event;
use Amin\AuditLogPro\Services\WPBridge;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostLogger implements Registrable {

	/**
	 * Post types we never want to audit — internal/system content,
	 * not anything a user consciously authored.
	 */
	private const IGNORED_POST_TYPES = array(
		'revision',
		'nav_menu_item',
		'customize_changeset',
		'oembed_cache',
		'wp_block',
	);

	/**
	 * Event repository.
	 *
	 * @var EventRepository
	 */
	private EventRepository $repository;

	/**
	 * WPBridge for native WP functions.
	 *
	 * @var WPBridge
	 */
	private WPBridge $wp;

	/**
	 * Hook loader.
	 *
	 * @var HookLoader
	 */
	private HookLoader $loader;

	public function __construct( EventRepository $repository, WPBridge $wp, HookLoader $loader ) {
		$this->wp         = $wp;
		$this->repository = $repository;
		$this->loader     = $loader;
	}

	public function register(): void {
		$this->loader->add_action( 'wp_after_insert_post', array( $this, 'after_insert' ), 10, 4 );
		$this->loader->add_action( 'transition_post_status', array( $this, 'status_changed' ), 10, 3 );
		$this->loader->add_action( 'trashed_post', array( $this, 'trashed' ) );
		$this->loader->add_action( 'untrashed_post', array( $this, 'restored' ) );
		$this->loader->add_action( 'before_delete_post', array( $this, 'deleted' ) );
	}

	/**
	 * Fires once a post has been inserted or updated (after all meta/terms are set).
	 *
	 * Handles CREATE and UPDATE only. Status transitions (draft -> publish etc.)
	 * are handled by status_changed() to avoid double-logging the same save.
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Post object.
	 * @param bool         $update      Whether this is an existing post being updated.
	 * @param WP_Post|null $post_before Post object before the update, or null on create.
	 */
	public function after_insert( int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;   // guard FIRST, before anything touches $post
		}

		if ( in_array( $post->post_type, self::IGNORED_POST_TYPES, true ) ) {
			return;
		}

		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$type = $update ? 'post_updated' : 'post_created';

		$message = $update
			? sprintf( '%s updated %s "%s"', $this->wp->actor_name(), $post->post_type, $post->post_title )
			: sprintf( '%s created a new %s "%s"', $this->wp->actor_name(), $post->post_type, $post->post_title );

		$meta = array(
			'post_type'   => $post->post_type,
			'post_status' => $post->post_status,
		);

		if ( $update && $post_before instanceof WP_Post && $post_before->post_title !== $post->post_title ) {
			$meta['old_title'] = $post_before->post_title;
		}

		$this->repository->insert(
			new Event(
				type       : $type,
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'post',
				object_id  : $post->ID,
				ip         : $this->wp->get_user_ip(),
				message    : $message,
				meta       : $meta,
			)
		);
	}

	/**
	 * Fires when a post's status changes (draft -> publish, publish -> pending, etc.).
	 *
	 * Trash/untrash transitions are excluded here and handled by trashed()/restored()
	 * so each has a single, unambiguous source of truth.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function status_changed( string $new_status, string $old_status, WP_Post $post ): void {
		if ( $new_status === $old_status ) {
			return;   // guard FIRST — no real transition happened
		}

		if ( in_array( $post->post_type, self::IGNORED_POST_TYPES, true ) ) {
			return;
		}

		// Creation is handled by after_insert(); trash by trashed()/restored().
		if ( in_array( $old_status, array( 'new', 'auto-draft' ), true ) ) {
			return;
		}

		if ( 'trash' === $new_status || 'trash' === $old_status ) {
			return;
		}

		$message = sprintf(
			'%s changed status of %s "%s" from %s to %s',
			$this->wp->actor_name(),
			$post->post_type,
			$post->post_title,
			$old_status,
			$new_status
		);

		$this->repository->insert(
			new Event(
				type       : 'post_status_changed',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'post',
				object_id  : $post->ID,
				ip         : $this->wp->get_user_ip(),
				message    : $message,
				meta       : array(
					'post_type'  => $post->post_type,
					'old_status' => $old_status,
					'new_status' => $new_status,
				),
			)
		);
	}

	/**
	 * Fires after a post is sent to trash.
	 *
	 * @param int $post_id ID of the trashed post.
	 */
	public function trashed( int $post_id ): void {
		$post = $this->wp->get_post( $post_id );

		if ( ! $post || in_array( $post->post_type, self::IGNORED_POST_TYPES, true ) ) {
			return;   // guard FIRST, before anything touches $post
		}

		$this->repository->insert(
			new Event(
				type       : 'post_trashed',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'post',
				object_id  : $post->ID,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s moved %s "%s" to trash', $this->wp->actor_name(), $post->post_type, $post->post_title ),
				meta       : array( 'post_type' => $post->post_type ),
			)
		);
	}

	/**
	 * Fires after a post is restored from trash.
	 *
	 * @param int $post_id ID of the restored post.
	 */
	public function restored( int $post_id ): void {
		$post = $this->wp->get_post( $post_id );

		if ( ! $post || in_array( $post->post_type, self::IGNORED_POST_TYPES, true ) ) {
			return;   // guard FIRST, before anything touches $post
		}

		$this->repository->insert(
			new Event(
				type       : 'post_restored',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'post',
				object_id  : $post->ID,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s restored %s "%s" from trash', $this->wp->actor_name(), $post->post_type, $post->post_title ),
				meta       : array( 'post_type' => $post->post_type ),
			)
		);
	}

	/**
	 * Fires immediately before a post (and its data) is permanently deleted.
	 * Must run BEFORE deletion — this is the only hook where $post is still fetchable.
	 *
	 * @param int $post_id ID of the post about to be deleted.
	 */
	public function deleted( int $post_id ): void {
		$post = $this->wp->get_post( $post_id );

		if ( ! $post || in_array( $post->post_type, self::IGNORED_POST_TYPES, true ) ) {
			return;   // guard FIRST, before anything touches $post
		}

		$this->repository->insert(
			new Event(
				type       : 'post_deleted',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'post',
				object_id  : $post->ID,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s permanently deleted %s "%s"', $this->wp->actor_name(), $post->post_type, $post->post_title ),
				meta       : array( 'post_type' => $post->post_type ),
			)
		);
	}
}
