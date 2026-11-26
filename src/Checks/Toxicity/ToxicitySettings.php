<?php

namespace Goodport\AutoModerator\Checks\Toxicity;

use Goodport\AutoModerator\Support\SettingsGuard;

/**
 * Resolved settings for the Perspective toxicity check.
 */
final readonly class ToxicitySettings
{
    /**
     * @param  array<int, string>  $fields
     */
    private function __construct(
        public array $fields,
        public ?string $language,
        public float $threshold,
        public int $maxChars,
        public bool $blocking,
        public PerspectiveServiceSettings $service,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromArray(array $settings, string $check): self
    {
        SettingsGuard::guardKeys($check, $settings, [
            'fields',
            'language',
            'threshold',
            'max_chars',
            'blocking',
            'service',
        ]);

        return new self(
            fields: SettingsGuard::stringList($check, $settings, 'fields', true),
            language: SettingsGuard::optionalString($check, $settings, 'language'),
            threshold: SettingsGuard::ratio($check, $settings, 'threshold', 0.7),
            maxChars: SettingsGuard::integer($check, $settings, 'max_chars', 10000, 1),
            blocking: SettingsGuard::bool($check, $settings, 'blocking', true),
            service: PerspectiveServiceSettings::fromArray(
                SettingsGuard::arrayValue($check, $settings, 'service'),
                $check,
            ),
        );
    }
}
