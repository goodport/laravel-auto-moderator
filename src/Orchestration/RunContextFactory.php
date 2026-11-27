<?php

namespace Goodport\AutoModerator\Orchestration;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Moderation\AuditContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Builds fallback run values from the Laravel environment.
 *
 * This is the only package class that reads the authenticated user, the
 * current request, and the application locale. Checks receive the resolved
 * values through the moderation context.
 */
final class RunContextFactory
{
    public function __construct(private readonly Repository $config) {}

    /**
     * Resolve the fallback language.
     */
    public function defaultLanguage(): string
    {
        return app()->getLocale();
    }

    /**
     * Resolve the run audit context.
     */
    public function audit(
        ?AuditContext $explicit,
        Model|Authenticatable|null $actor,
        ?Model $subject,
    ): ?AuditContext {
        if ($explicit !== null) {
            return $explicit;
        }

        $enabled = $this->config->get('auto-moderator.audit.enabled', true);

        if (! is_bool($enabled)) {
            throw InvalidConfigurationException::invalidSetting('audit.enabled', 'must be a boolean.');
        }

        if (! $enabled) {
            return null;
        }

        $actor ??= Auth::user();

        return new AuditContext(
            actorType: $this->actorType($actor),
            actorId: $this->actorId($actor),
            subjectType: $subject?->getMorphClass(),
            subjectId: $subject !== null ? (string) $subject->getKey() : null,
            requestUrl: request()->fullUrl(),
        );
    }

    private function actorType(Model|Authenticatable|null $actor): ?string
    {
        return match (true) {
            $actor === null => null,
            $actor instanceof Model => $actor->getMorphClass(),
            default => $actor::class,
        };
    }

    private function actorId(Model|Authenticatable|null $actor): ?string
    {
        return match (true) {
            $actor === null => null,
            $actor instanceof Model => (string) $actor->getKey(),
            default => (string) $actor->getAuthIdentifier(),
        };
    }
}
