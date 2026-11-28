<?php

namespace Goodport\AutoModerator\Support;

/**
 * String value helpers for stored moderation text.
 *
 * @internal
 */
final class ModerationValue
{
    public static function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (self::hasValue($value)) {
                return $value;
            }
        }

        return null;
    }

    public static function hasValue(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
