<?php
namespace Amin\AuditLogPro;

/**
 * Contract for registering hooks
 *
 * All hooks class can use it
 * Any class will add action filter, add action
 * must use this Interface
 *
 * @since 1.0.0
 * @package AuditLogPro
 * @author Al Amin <hmalaminmb4@gmail.com>
 */
interface RegistrationInterface {
	/**
	 * Register contract
	 *
	 * @return void
	 */
	public function register_hook(): void;
}
