<?php

namespace Goodport\AutoModerator\Checks\StopWords;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Support\SettingsGuard;

/**
 * Resolved settings for the stop word check.
 */
final readonly class StopWordsSettings
{
    /**
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $words
     */
    private function __construct(
        public array $fields,
        public array $words,
        public bool $blocking,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromArray(array $settings, string $check): self
    {
        SettingsGuard::guardKeys($check, $settings, ['fields', 'words', 'blocking']);

        return new self(
            fields: SettingsGuard::stringList($check, $settings, 'fields', true),
            words: self::normalizeWords(
                SettingsGuard::stringList($check, $settings, 'words', true),
                $check,
            ),
            blocking: SettingsGuard::bool($check, $settings, 'blocking', true),
        );
    }

    /**
     * Normalizes a value for case-insensitive phrase matching.
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * @param  array<int, string>  $words
     * @return array<int, string>
     */
    private static function normalizeWords(array $words, string $check): array
    {
        $normalized = [];

        foreach ($words as $word) {
            $value = self::normalize($word);

            if ($value === '') {
                throw InvalidConfigurationException::invalidSetting(
                    $check.'.words',
                    'must not contain blank entries.',
                );
            }

            if (! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
