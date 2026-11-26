<?php

namespace Goodport\AutoModerator\Checks\StopWords;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Contracts\ValidatesSettings;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Support\TextFields;

/**
 * Rejects configured stop words and phrases.
 *
 * The package contains no built-in list. The application supplies every word in
 * the check settings.
 */
final class StopWordsCheck implements ModerationCheck, ValidatesSettings
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function validateSettings(string $check, array $settings): void
    {
        StopWordsSettings::fromArray($settings, $check);
    }

    public function check(ModerationContext $context): CheckResult
    {
        $settings = StopWordsSettings::fromArray($context->settings, $context->check);
        $texts = TextFields::values($context, $settings->fields);

        $findings = [];
        $matches = [];

        foreach ($texts as $field => $text) {
            $normalizedText = StopWordsSettings::normalize($text);

            foreach ($settings->words as $word) {
                $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u';

                if (preg_match($pattern, $normalizedText) !== 1) {
                    continue;
                }

                $matches[] = ['field' => $field, 'word' => $word];
                $findings[] = new Finding(
                    code: 'stop_word',
                    message: sprintf('Stop word "%s" detected in field "%s".', $word, $field),
                    severity: FindingSeverity::High,
                    blocking: $settings->blocking,
                    details: ['field' => $field, 'word' => $word],
                );
            }
        }

        $details = ['matched_words' => $matches];

        if ($findings === []) {
            return CheckResult::passedResult($context->check, details: $details);
        }

        if ($settings->blocking) {
            return CheckResult::rejectedResult(
                $context->check,
                sprintf('Stop words detected: %d.', count($findings)),
                $findings,
                $details,
            );
        }

        return CheckResult::passedResult($context->check, $findings, $details);
    }
}
