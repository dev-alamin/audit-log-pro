<?php

namespace Amin\AuditLogPro\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Event {

	public function __construct(
		public readonly string $type,
		public readonly int $actor_id,
		public readonly string $object_type,
		public readonly int $object_id,
		public readonly string $ip,
		public readonly string $message,
		public readonly array $meta = array(),
	) {}
}
