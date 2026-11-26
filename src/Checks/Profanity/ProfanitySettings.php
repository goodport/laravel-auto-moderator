<?php

namespace Goodport\AutoModerator\Checks\Profanity;

use Goodport\AutoModerator\Support\SettingsGuard;

/**
 * Resolved settings for the profanity check.
 */
final readonly class ProfanitySettings
{
    /**
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $languages
     */
    private function __construct(
        public array $fields,
        public array $languages,
        public bool $blocking,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromArray(array $settings, string $check): self
    {
        SettingsGuard::guardKeys($check, $settings, ['fields', 'languages', 'blocking']);

        $languages = SettingsGuard::stringList($check, $settings, 'languages', true);

        foreach ($languages as $language) {
            ProfanityDictionary::guardLanguage($check, $language);
        }

        return new self(
            fields: SettingsGuard::stringList($check, $settings, 'fields', true),
            languages: $languages,
            blocking: SettingsGuard::bool($check, $settings, 'blocking', true),
        );
    }
}
