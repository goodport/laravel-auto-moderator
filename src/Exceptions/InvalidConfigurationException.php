<?php

namespace Goodport\AutoModerator\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Invalid package or profile configuration.
 */
final class InvalidConfigurationException extends RuntimeException
{
    public static function invalidSetting(string $setting, string $message, ?Throwable $previous = null): self
    {
        return new self(sprintf('Invalid auto-moderator setting "%s": %s', $setting, $message), 0, $previous);
    }

    public static function unknownProfile(string $profile): self
    {
        return new self(sprintf('Auto-moderator profile "%s" is not configured.', $profile));
    }

    public static function invalidProfile(string $profile, string $message): self
    {
        return new self(sprintf('Auto-moderator profile "%s" is invalid: %s', $profile, $message));
    }

    public static function unknownCheck(string $check): self
    {
        return new self(sprintf('Auto-moderator check "%s" is not registered.', $check));
    }

    public static function fallbackCheckMissing(string $check): self
    {
        return new self(sprintf(
            'The fallback AI check "%s" is not registered in auto-moderator.checks.',
            $check,
        ));
    }

    public static function invalidCheck(string $check, string $message, ?Throwable $previous = null): self
    {
        return new self(sprintf('Auto-moderator check "%s" is invalid: %s', $check, $message), 0, $previous);
    }

    public static function unknownService(string $service): self
    {
        return new self(sprintf('Auto-moderator service "%s" is not configured.', $service));
    }

    public static function missingLanguage(string $check): self
    {
        return new self(sprintf(
            'Auto-moderator check "%s" requires a language. Set the check `language` setting or pass ->language().',
            $check,
        ));
    }

    /**
     * @param  array<int, string>  $supported
     */
    public static function unknownAlphabet(string $check, string $language, array $supported): self
    {
        return new self(sprintf(
            'Auto-moderator check "%s" does not support language "%s". Configured alphabets: %s.',
            $check,
            $language,
            $supported === [] ? '(none)' : implode(', ', $supported),
        ));
    }

    public static function unknownModeratableType(string $type): self
    {
        return new self(sprintf('The moderation morph type "%s" does not resolve to an Eloquent model.', $type));
    }

    public static function notModeratable(string $class): self
    {
        return new self(sprintf('The model "%s" must implement the Moderatable contract.', $class));
    }

    public static function scenarioMismatch(string $expected, string $actual): self
    {
        return new self(sprintf(
            'The moderation scenario "%s" does not match the model scenario "%s".',
            $expected,
            $actual,
        ));
    }

    public static function invalidSource(string $class): self
    {
        return new self(sprintf('The moderation source "%s" must implement the ModerationSource contract.', $class));
    }

    public static function invalidSourceModel(string $source): self
    {
        return new self(sprintf(
            'The moderation source "%s" returned a model that is not Moderatable.',
            $source,
        ));
    }
}
