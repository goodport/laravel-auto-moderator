<?php

namespace Goodport\AutoModerator\Moderation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * Build a context from the actor and subject models.
     */
    public static function fromActorAndSubject(
        Model|Authenticatable|null $actor,
        ?Model $subject,
        ?string $requestUrl = null,
    ): self {
        return new self(
            actorType: self::actorType($actor),
            actorId: self::actorId($actor),
            subjectType: $subject?->getMorphClass(),
            subjectId: $subject !== null ? (string) $subject->getKey() : null,
            requestUrl: $requestUrl,
        );
    }

    /**
     * @return array{
     *     actor_type: ?string,
     *     actor_id: ?string,
     *     subject_type: ?string,
     *     subject_id: ?string,
     *     request_url: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'request_url' => $this->requestUrl,
        ];
    }

    private static function actorType(Model|Authenticatable|null $actor): ?string
    {
        return match (true) {
            $actor === null => null,
            $actor instanceof Model => $actor->getMorphClass(),
            default => $actor::class,
        };
    }

    private static function actorId(Model|Authenticatable|null $actor): ?string
    {
        return match (true) {
            $actor === null => null,
            $actor instanceof Model => (string) $actor->getKey(),
            default => (string) $actor->getAuthIdentifier(),
        };
    }
}
