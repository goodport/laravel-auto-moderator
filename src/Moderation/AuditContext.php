<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Audit context attached to a profile run.
 *
 * The package never reads the current request or the authenticated user. The
 * application supplies the context explicitly.
 */
final readonly class AuditContext
{
    public function __construct(
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $requestUrl = null,
    ) {}
}
