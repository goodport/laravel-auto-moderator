<?php

namespace Goodport\AutoModerator\Checks\Toxicity;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Contracts\ValidatesSettings;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Support\TextFields;
use Illuminate\Support\Facades\Http;
use stdClass;
use Throwable;

/**
 * Adapter for the Perspective toxicity API.
 *
 * Transport and response failures produce an undecided result, never a pass.
 */
final class ToxicityCheck implements ModerationCheck, ValidatesSettings
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function validateSettings(string $check, array $settings): void
    {
        ToxicitySettings::fromArray($settings, $check);
    }

    public function check(ModerationContext $context): CheckResult
    {
        $settings = ToxicitySettings::fromArray($context->settings, $context->check);
        $language = $context->language ?? $settings->language;

        if ($language === null) {
            throw InvalidConfigurationException::missingLanguage($context->check);
        }

        $texts = TextFields::values($context, $settings->fields);

        $findings = [];
        $scores = [];
        $errors = [];
        $truncatedFields = [];

        foreach ($texts as $field => $text) {
            if (mb_strlen($text, 'UTF-8') > $settings->maxChars) {
                $text = mb_substr($text, 0, $settings->maxChars, 'UTF-8');
                $truncatedFields[] = $field;
            }

            try {
                $response = Http::timeout($settings->service->timeout)
                    ->asJson()
                    ->withQueryParameters(['key' => $settings->service->key])
                    ->post($settings->service->url, [
                        'comment' => ['text' => $text],
                        'languages' => [$language],
                        'requestedAttributes' => ['TOXICITY' => new stdClass],
                    ]);
            } catch (Throwable $exception) {
                $errors[] = sprintf(
                    'Perspective API request for field "%s" failed: %s',
                    $field,
                    $this->redact($exception->getMessage(), $settings->service->key),
                );

                continue;
            }

            if (! $response->successful()) {
                $errors[] = sprintf(
                    'Perspective API returned HTTP %d for field "%s".',
                    $response->status(),
                    $field,
                );

                continue;
            }

            $score = $response->json('attributeScores.TOXICITY.summaryScore.value');

            if (! is_numeric($score)) {
                $errors[] = sprintf(
                    'Perspective API returned an invalid response for field "%s".',
                    $field,
                );

                continue;
            }

            $score = (float) $score;
            $scores[$field] = $score;

            if ($score < $settings->threshold) {
                continue;
            }

            $findings[] = new Finding(
                code: 'toxicity',
                message: sprintf(
                    'Field "%s" has a toxicity score of %.2f, at or above the %.2f threshold.',
                    $field,
                    $score,
                    $settings->threshold,
                ),
                severity: FindingSeverity::High,
                blocking: $settings->blocking,
                details: [
                    'field' => $field,
                    'score' => $score,
                    'threshold' => $settings->threshold,
                    'language' => $language,
                    'attribute' => 'TOXICITY',
                ],
            );
        }

        $details = [
            'language' => $language,
            'threshold' => $settings->threshold,
            'max_chars' => $settings->maxChars,
            'scores' => $scores,
            'truncated' => $truncatedFields,
        ];

        if ($errors !== []) {
            $details['errors'] = $errors;
        }

        if ($findings !== [] && $settings->blocking) {
            return CheckResult::rejectedResult(
                $context->check,
                sprintf('Toxicity score is at or above the threshold in %d field(s).', count($findings)),
                $findings,
                $details,
            );
        }

        if ($errors !== []) {
            return CheckResult::undecidedResult(
                $context->check,
                $errors[0],
                findings: $findings,
                details: $details,
            );
        }

        if ($findings !== []) {
            return CheckResult::passedResult($context->check, $findings, $details);
        }

        return CheckResult::passedResult($context->check, details: $details);
    }

    private function redact(string $message, string $secret): string
    {
        return str_replace(
            [$secret, urlencode($secret), rawurlencode($secret)],
            '[redacted]',
            $message,
        );
    }
}
