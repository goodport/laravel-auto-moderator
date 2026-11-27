<?php

namespace Goodport\AutoModerator\Exceptions;

use RuntimeException;

/**
 * Missing or invalid moderation input.
 */
final class InvalidInputException extends RuntimeException
{
    public static function missingField(string $check, string $field): self
    {
        return new self(sprintf('Auto-moderator check "%s" requires input field "%s".', $check, $field));
    }

    public static function invalidText(string $check, string $field): self
    {
        return new self(sprintf(
            'Input field "%s" for auto-moderator check "%s" must be a string.',
            $field,
            $check,
        ));
    }

    public static function invalidEncoding(string $check, string $field): self
    {
        return new self(sprintf(
            'Input field "%s" for auto-moderator check "%s" must be valid UTF-8.',
            $field,
            $check,
        ));
    }

    public static function emptyLanguage(): self
    {
        return new self('The moderation language must be a non-empty string.');
    }

    public static function emptyModerationFieldKey(): self
    {
        return new self('A moderation case field key must be a non-empty string.');
    }

    public static function emptyModerationFieldLabel(): self
    {
        return new self('A moderation case field label must be a non-empty string.');
    }

    public static function emptyModerationFieldProfile(): self
    {
        return new self('A moderation case field profile must be a non-empty string.');
    }

    public static function duplicateModerationFieldKey(string $key): self
    {
        return new self(sprintf('A moderation case field with key "%s" already exists.', $key));
    }
}
