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
}
