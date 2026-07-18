<?php

namespace Amin\AuditLogPro\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DTO for Event
 *
 * Data transfer object for {EventRepository}.
 *
 * @since 1.0.0
 * @package AuditLogPro\Database
 * @author Al Amin <hmalaminmb4@gmail.com>
 */
final class Event {

	/**
	 * All read only data only for transfering.
	 *
	 * @param string  $type
	 * @param integer $actor_id
	 * @param string  $object_type
	 * @param integer $object_id
	 * @param string  $ip
	 * @param string  $message
	 * @param array   $meta
	 */
	public function __construct(
		public readonly string $type,
		public readonly int $actor_id,
		public readonly string $object_type,
		public readonly int $object_id,
		public readonly string $ip,
		public readonly string $message,
		public readonly array $meta = array()
	) {}
}
