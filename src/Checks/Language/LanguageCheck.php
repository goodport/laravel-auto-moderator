<?php

namespace Goodport\AutoModerator\Checks\Language;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Contracts\ValidatesSettings;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Support\TextFields;

/**
 * Checks the ratio of characters that belong to the expected alphabet.
 *
 * The run language is resolved by the runner from ->language(), the check
 * language setting, and the application locale fallback.
 */
final class LanguageCheck implements ModerationCheck, ValidatesSettings
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function validateSettings(string $check, array $settings): void
    {
        LanguageSettings::fromArray($settings, $check);
    }

    public function check(ModerationContext $context): CheckResult
    {
        $settings = LanguageSettings::fromArray($context->settings, $context->check);
        $language = $context->language ?? $settings->language;

        if ($language === null) {
            throw InvalidConfigurationException::missingLanguage($context->check);
        }

        $alphabet = $settings->alphabets[$language] ?? null;

        if ($alphabet === null) {
            throw InvalidConfigurationException::unknownAlphabet(
                $context->check,
                $language,
                array_keys($settings->alphabets),
            );
        }

        $alphabetSet = [];

        foreach (mb_str_split(mb_strtolower($alphabet, 'UTF-8')) as $letter) {
            $alphabetSet[$letter] = true;
        }

        $texts = TextFields::values($context, $settings->fields);

        $fieldDetails = [];
        $findings = [];
        $analyzed = 0;

        foreach ($texts as $field => $text) {
            if (mb_strlen($text, 'UTF-8') < $settings->minLength) {
                $fieldDetails[$field] = [
                    'skipped' => true,
                    'reason' => 'shorter_than_min_length',
                ];

                continue;
            }

            $letters = preg_match_all('/\p{L}/u', $text, $matches);

            if ($letters === 0) {
                $fieldDetails[$field] = [
                    'skipped' => true,
                    'reason' => 'no_letters',
                    'letters' => 0,
                    'matching' => 0,
                    'ratio' => 0.0,
                ];

                continue;
            }

            $matching = 0;

            foreach ($matches[0] as $letter) {
                if (isset($alphabetSet[mb_strtolower($letter, 'UTF-8')])) {
                    $matching++;
                }
            }

            $ratio = (float) ($matching / $letters);

            $fieldDetails[$field] = [
                'skipped' => false,
                'letters' => $letters,
                'matching' => $matching,
                'ratio' => $ratio,
            ];

            $analyzed++;

            if ($ratio >= $settings->threshold) {
                continue;
            }

            $findings[] = new Finding(
                code: 'language_ratio',
                message: sprintf(
                    'Field "%s" has an alphabet ratio of %.2f, below the %.2f threshold.',
                    $field,
                    $ratio,
                    $settings->threshold,
                ),
                severity: FindingSeverity::Medium,
                blocking: $settings->blocking,
                details: [
                    'field' => $field,
                    'language' => $language,
                    'ratio' => $ratio,
                    'threshold' => $settings->threshold,
                    'letters' => $letters,
                    'matching' => $matching,
                ],
            );
        }

        $details = [
            'language' => $language,
            'min_length' => $settings->minLength,
            'threshold' => $settings->threshold,
            'fields' => $fieldDetails,
        ];

        if ($analyzed === 0) {
            return CheckResult::skippedResult(
                $context->check,
                'No field is long enough to analyze.',
                $details,
            );
        }

        if ($findings === []) {
            return CheckResult::passedResult($context->check, details: $details);
        }

        if ($settings->blocking) {
            return CheckResult::rejectedResult(
                $context->check,
                sprintf('Alphabet ratio is below the threshold in %d field(s).', count($findings)),
                $findings,
                $details,
            );
        }

        return CheckResult::passedResult($context->check, $findings, $details);
    }
}
