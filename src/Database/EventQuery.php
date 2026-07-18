<?php
namespace Amin\AuditLogPro\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EventQuery {
	public function __construct(
		public readonly string $event_type = '',
		public readonly int $actor_id = 0,
		public readonly string $object_type = '',
		public readonly int $object_id = 0,
		public readonly ?string $created_after = null,
		public readonly ?string $created_before = null,
		public readonly ?int $cursor_id = null,
		public readonly int $per_page = 20,
	) {}
}
