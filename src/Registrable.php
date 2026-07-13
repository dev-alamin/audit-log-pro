<?php
namespace Amin\AuditLogPro;

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
interface Registrable {
	public function register(): void;
}
