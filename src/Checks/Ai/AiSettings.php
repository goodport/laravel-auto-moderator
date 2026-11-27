<?php

namespace Goodport\AutoModerator\Checks\Ai;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Support\SettingsGuard;

/**
 * Resolved settings for the AI adapter.
 */
final readonly class AiSettings
{
    /**
     * @param  array<int, string>  $fields
     */
    private function __construct(
        public AiMode $mode,
        public string $key,
        public array $fields,
        public ?string $language,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromArray(array $settings, string $check): self
    {
        SettingsGuard::guardKeys($check, $settings, ['check', 'policy', 'fields', 'language']);

        $hasCheck = array_key_exists('check', $settings);
        $hasPolicy = array_key_exists('policy', $settings);

        if ($hasCheck && $hasPolicy) {
            throw InvalidConfigurationException::invalidSetting(
                $check,
                '`check` and `policy` cannot be used together.',
            );
        }

        if (! $hasCheck && ! $hasPolicy) {
            throw InvalidConfigurationException::invalidSetting(
                $check,
                'one of `check` or `policy` is required.',
            );
        }

        $language = SettingsGuard::optionalString($check, $settings, 'language');

        if ($hasCheck) {
            if (array_key_exists('fields', $settings)) {
                throw InvalidConfigurationException::invalidSetting(
                    $check.'.fields',
                    'is not allowed when `check` is used; the AI check declares its own fields.',
                );
            }

            $key = $settings['check'];

            if (! is_string($key) || trim($key) === '') {
                throw InvalidConfigurationException::invalidSetting(
                    $check.'.check',
                    'must be a non-empty AI check key.',
                );
            }

            return new self(AiMode::Check, trim($key), [], $language);
        }

        $key = $settings['policy'];

        if (! is_string($key) || trim($key) === '') {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.policy',
                'must be a non-empty policy key.',
            );
        }

        return new self(
            mode: AiMode::Policy,
            key: trim($key),
            fields: SettingsGuard::stringList($check, $settings, 'fields', true),
            language: $language,
        );
    }
}
