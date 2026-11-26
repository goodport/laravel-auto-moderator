<?php

namespace Goodport\AutoModerator\Checks\Profanity;

use ConsoleTVs\Profanity\Builder;
use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Contracts\ValidatesSettings;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Support\TextFields;

/**
 * Detects profanity with the configured dictionaries.
 *
 * Dictionary languages are independent from the run language. Passing
 * ->language() must never disable a dictionary a profile relies on.
 */
final class ProfanityCheck implements ModerationCheck, ValidatesSettings
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function validateSettings(string $check, array $settings): void
    {
        ProfanitySettings::fromArray($settings, $check);
    }

    public function check(ModerationContext $context): CheckResult
    {
        $settings = ProfanitySettings::fromArray($context->settings, $context->check);
        $texts = TextFields::values($context, $settings->fields);

        $findings = [];
        $matches = [];

        foreach ($texts as $field => $text) {
            $badWords = Builder::blocker($text, '****', $settings->languages)->badWords();

            foreach ($badWords as $badWord) {
                $word = (string) $badWord['word'];
                $language = (string) $badWord['language'];

                $matches[] = ['field' => $field, 'word' => $word, 'language' => $language];
                $findings[] = new Finding(
                    code: 'profanity',
                    message: sprintf('Profanity "%s" (%s) detected in field "%s".', $word, $language, $field),
                    severity: FindingSeverity::High,
                    blocking: $settings->blocking,
                    details: ['field' => $field, 'word' => $word, 'language' => $language],
                );
            }
        }

        $details = ['matched_words' => $matches, 'languages' => $settings->languages];

        if ($findings === []) {
            return CheckResult::passedResult($context->check, details: $details);
        }

        if ($settings->blocking) {
            return CheckResult::rejectedResult(
                $context->check,
                sprintf('Profanity detected: %d.', count($findings)),
                $findings,
                $details,
            );
        }

        return CheckResult::passedResult($context->check, $findings, $details);
    }
}
