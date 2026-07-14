<?php
namespace Amin\AuditLogPro\Loggers;

/**
 * Contract for register
 *
 * All hooks class can use it
 * Any class will add action filter, add action
 * must use this Interface
 *
 * @since 1.0.0
 * @package AuditLogPro
 * @author Al Amin <hmalaminmb4@gmail.com>
 */
interface LoggerInterface {
	/**
	 * Register contract
	 *
	 * @return void
	 */
	public function register(): void;
}
