<?php

namespace Goodport\AutoModerator\Checks\Ai;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Support\SettingsGuard;

/**
 * Resolved settings for the AI policy adapter.
 */
final readonly class AiSettings
{
    /**
     * @param  array<int, string>  $fields
     */
    private function __construct(
        public string $policy,
        public array $fields,
        public ?string $language,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromArray(array $settings, string $check): self
    {
        SettingsGuard::guardKeys($check, $settings, ['policy', 'fields', 'language']);

        $policy = $settings['policy'] ?? null;

        if (! is_string($policy) || trim($policy) === '') {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.policy',
                'must be a non-empty policy key.',
            );
        }

        return new self(
            policy: $policy,
            fields: SettingsGuard::stringList($check, $settings, 'fields', true),
            language: SettingsGuard::optionalString($check, $settings, 'language'),
        );
    }
}
