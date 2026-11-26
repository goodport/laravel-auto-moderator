<?php

namespace Goodport\AutoModerator\Checks\Profanity;

use ConsoleTVs\Profanity\Builder;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;

/**
 * Languages supported by the installed profanity dictionaries.
 */
final class ProfanityDictionary
{
    /**
     * @var array<int, string>|null
     */
    private static ?array $languages = null;

    /**
     * @return array<int, string>
     */
    public static function languages(): array
    {
        if (self::$languages !== null) {
            return self::$languages;
        }

        $languages = [];

        foreach (Builder::blocker('', '****')->dictionary as $entry) {
            if (! is_array($entry) || ! isset($entry['language']) || ! is_string($entry['language'])) {
                throw new InvalidConfigurationException('The profanity dictionary is malformed.');
            }

            if (! in_array($entry['language'], $languages, true)) {
                $languages[] = $entry['language'];
            }
        }

        sort($languages);

        return self::$languages = $languages;
    }

    public static function guardLanguage(string $check, string $language): void
    {
        if (in_array($language, self::languages(), true)) {
            return;
        }

        throw InvalidConfigurationException::invalidSetting(
            $check.'.languages',
            sprintf(
                'unsupported language "%s". Available: %s.',
                $language,
                implode(', ', self::languages()),
            ),
        );
    }
}
