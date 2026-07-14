<?php
namespace Amin\AuditLogPro\Loggers;

use Amin\AuditLogPro\Core\HookLoader;
use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\Services\WPBridge;
use Amin\AuditLogPro\RegistrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


abstract class AbstractLogger implements RegistrationInterface {
	/**
	 * Event repository.
	 *
	 * @var EventRepository
	 */
	protected EventRepository $repository;

	/**
	 * WPBridge for native WP functions.
	 *
	 * @var WPBridge
	 */
	protected WPBridge $wp;

	/**
	 * Hook loader.
	 *
	 * @var HookLoader
	 */
	protected HookLoader $loader;

	public function __construct( EventRepository $repository, WPBridge $wp, HookLoader $loader ) {
		$this->wp         = $wp;
		$this->repository = $repository;
		$this->loader     = $loader;
	}
}
