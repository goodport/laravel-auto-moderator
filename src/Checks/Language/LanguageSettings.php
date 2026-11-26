<?php

namespace Goodport\AutoModerator\Checks\Language;

use Goodport\AutoModerator\Support\SettingsGuard;

/**
 * Resolved settings for the alphabet ratio check.
 */
final readonly class LanguageSettings
{
    /**
     * @param  array<int, string>  $fields
     * @param  array<string, string>  $alphabets
     */
    private function __construct(
        public array $fields,
        public ?string $language,
        public int $minLength,
        public float $threshold,
        public array $alphabets,
        public bool $blocking,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromArray(array $settings, string $check): self
    {
        SettingsGuard::guardKeys($check, $settings, [
            'fields',
            'language',
            'min_length',
            'threshold',
            'alphabets',
            'blocking',
        ]);

        return new self(
            fields: SettingsGuard::stringList($check, $settings, 'fields', true),
            language: SettingsGuard::optionalString($check, $settings, 'language'),
            minLength: SettingsGuard::integer($check, $settings, 'min_length', 20, 0),
            threshold: SettingsGuard::ratio($check, $settings, 'threshold', 0.6),
            alphabets: SettingsGuard::stringMap($check, $settings, 'alphabets', true),
            blocking: SettingsGuard::bool($check, $settings, 'blocking', true),
        );
    }
}
