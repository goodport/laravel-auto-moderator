<?php

namespace Goodport\AutoModerator\Checks\Toxicity;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Support\SettingsGuard;

/**
 * Perspective API connection settings.
 */
final readonly class PerspectiveServiceSettings
{
    private function __construct(
        public string $url,
        public string $key,
        public int $timeout,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromArray(array $settings, string $check): self
    {
        SettingsGuard::guardKeys($check.'.service', $settings, ['url', 'key', 'timeout']);

        $url = $settings['url'] ?? null;

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.service.url',
                'must be a valid URL.',
            );
        }

        $key = $settings['key'] ?? null;

        if (! is_string($key) || trim($key) === '') {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.service.key',
                'must be configured. Set auto-moderator.services.perspective.key.',
            );
        }

        return new self(
            url: $url,
            key: $key,
            timeout: SettingsGuard::integer($check.'.service', $settings, 'timeout', 10, 1),
        );
    }
}
