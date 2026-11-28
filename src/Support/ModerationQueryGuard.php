<?php

namespace Goodport\AutoModerator\Support;

use Goodport\AutoModerator\Moderation\ModerationFieldOutcome;
use Goodport\AutoModerator\Moderation\ModerationOutcome;
use InvalidArgumentException;

/**
 * Validates public status, state, scenario, and field key values.
 *
 * @internal
 */
final class ModerationQueryGuard
{
    /**
     * @return array<int, string>
     */
    public static function statuses(string|array $status): array
    {
        $statuses = is_array($status) ? array_values($status) : [$status];

        if ($statuses === []) {
            throw new InvalidArgumentException('At least one moderation status is required.');
        }

        foreach ($statuses as $value) {
            if (! is_string($value) || ! in_array($value, ModerationOutcome::statuses(), true)) {
                throw new InvalidArgumentException('Unknown moderation status.');
            }
        }

        return $statuses;
    }

    public static function state(?string $state): ?string
    {
        if ($state === null) {
            return null;
        }

        if (! in_array($state, ModerationFieldOutcome::states(), true)) {
            throw new InvalidArgumentException('Unknown moderation field state.');
        }

        return $state;
    }

    public static function scenario(?string $scenario): ?string
    {
        if ($scenario === null) {
            return null;
        }

        return self::scenarioName($scenario);
    }

    public static function scenarioName(string $scenario): string
    {
        if (trim($scenario) === '') {
            throw new InvalidArgumentException('The moderation scenario must be a non-empty string.');
        }

        return $scenario;
    }

    public static function fieldKey(string $fieldKey): string
    {
        if (trim($fieldKey) === '') {
            throw new InvalidArgumentException('The moderation field key must be a non-empty string.');
        }

        return $fieldKey;
    }
}
