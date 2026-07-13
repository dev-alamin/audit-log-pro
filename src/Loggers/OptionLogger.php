<?php
namespace Amin\AuditLogPro\Loggers;

use Amin\AuditLogPro\Core\HookLoader;
use Amin\AuditLogPro\Registrable;
use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\Database\Event;
use Amin\AuditLogPro\Services\WPBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OptionLogger implements Registrable {

	/**
	 * Option name prefixes that fire on nearly every request and carry
	 * no meaningful "someone did something" signal. Never audited.
	 */
	private const IGNORED_PREFIXES = array(
		'_transient_',
		'_site_transient_',
		'_wp_session_',
		'cron',
		'auto_updater.lock',
		'db_upgraded',
		'recently_activated',
		'_site_transient_update_',
		'rewrite_rules',
		'wp_admin_email_check_data',
		'health-check-site-status-result',
	);

	/**
	 * Exact option names to ignore even though they don't match a prefix above.
	 */
	private const IGNORED_OPTIONS = array(
		'cron',
		'doing_cron',
		'_wp_session',
	);

	/**
	 * Options whose values may contain secrets. We log that a change
	 * happened, never the actual value.
	 */
	private const SENSITIVE_PATTERNS = array(
		'key',
		'secret',
		'password',
		'token',
		'auth',
		'api',
		'private',
		'credential',
	);

	/**
	 * Options security-relevant enough that we log the full before/after
	 * even though they're not "sensitive" in the secret-value sense.
	 */
	private const HIGH_SIGNAL_OPTIONS = array(
		'active_plugins',
		'template',
		'stylesheet',
		'users_can_register',
		'default_role',
		'admin_email',
		'siteurl',
		'home',
		'blogname',
		'permalink_structure',
		'WPLANG',
	);

	/**
	 * Max length for a logged value before we truncate it. Options can hold
	 * arbitrarily large serialized blobs (widget data, theme mods, etc).
	 */
	private const VALUE_MAX_LENGTH = 200;

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
		$this->loader->add_action( 'added_option', array( $this, 'added' ), 10, 2 );
		$this->loader->add_action( 'updated_option', array( $this, 'updated' ), 10, 3 );
		$this->loader->add_action( 'deleted_option', array( $this, 'deleted' ), 10, 1 );
	}

	/**
	 * Fires after a new option has been added.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	public function added( string $option, $value ): void {
		if ( $this->is_ignored( $option ) ) {
			return;   // guard FIRST, before anything touches $value
		}

		$this->repository->insert(
			new Event(
				type       : 'option_added',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'option',
				object_id  : 0,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s added option "%s"', $this->wp->actor_name(), $option ),
				meta       : array(
					'option' => $option,
					'value'  => $this->prepare_value( $option, $value ),
				),
			)
		);
	}

	/**
	 * Fires after the value of an existing option has been changed.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old option value.
	 * @param mixed  $value     New option value.
	 */
	public function updated( string $option, $old_value, $value ): void {
		if ( $this->is_ignored( $option ) ) {
			return;   // guard FIRST, before anything touches $old_value/$value
		}

		if ( $old_value === $value ) {
			return;   // no real change — WP's own check is loose (==), ours isn't
		}

		$this->repository->insert(
			new Event(
				type       : 'option_updated',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'option',
				object_id  : 0,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s updated option "%s"', $this->wp->actor_name(), $option ),
				meta       : array(
					'option'    => $option,
					'old_value' => $this->prepare_value( $option, $old_value ),
					'value'     => $this->prepare_value( $option, $value ),
				),
			)
		);
	}

	/**
	 * Fires after an option has been deleted.
	 *
	 * @param string $option Name of the deleted option.
	 */
	public function deleted( string $option ): void {
		if ( $this->is_ignored( $option ) ) {
			return;   // guard FIRST
		}

		$this->repository->insert(
			new Event(
				type       : 'option_deleted',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'option',
				object_id  : 0,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s deleted option "%s"', $this->wp->actor_name(), $option ),
				meta       : array( 'option' => $option ),
			)
		);
	}

	/**
	 * Whether this option is noise we never want in the log.
	 */
	private function is_ignored( string $option ): bool {
		if ( in_array( $option, self::IGNORED_OPTIONS, true ) ) {
			return true;
		}

		foreach ( self::IGNORED_PREFIXES as $prefix ) {
			if ( str_starts_with( $option, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this option's value looks like it could hold a secret.
	 */
	private function is_sensitive( string $option ): bool {
		$option_lower = strtolower( $option );

		foreach ( self::SENSITIVE_PATTERNS as $pattern ) {
			if ( str_contains( $option_lower, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Prepares an option value for storage in event meta: redacts secrets,
	 * flattens non-scalars to a readable form, and truncates long values.
	 *
	 * @param string $option Option name, used to decide redaction/verbosity.
	 * @param mixed  $value  Raw option value.
	 * @return string
	 */
	private function prepare_value( string $option, $value ): string {
		if ( $this->is_sensitive( $option ) ) {
			return '[redacted]';
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value );
			if ( false === $value ) {
				return '[unserializable]';
			}
		} elseif ( is_bool( $value ) ) {
			$value = $value ? 'true' : 'false';
		} elseif ( null === $value ) {
			$value = '';
		} else {
			$value = (string) $value;
		}

		$max_length = in_array( $option, self::HIGH_SIGNAL_OPTIONS, true )
			? self::VALUE_MAX_LENGTH * 2
			: self::VALUE_MAX_LENGTH;

		if ( strlen( $value ) > $max_length ) {
			$value = substr( $value, 0, $max_length ) . '… (truncated)';
		}

		return $value;
	}
}
