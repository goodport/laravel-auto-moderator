<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Audit context attached to a profile run.
 *
 * This is a neutral DTO. RunContextFactory builds the default context from the
 * authenticated user, the current request, and the optional subject; callers
 * may pass an explicit context instead.
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
