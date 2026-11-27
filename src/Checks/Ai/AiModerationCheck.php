<?php

namespace Goodport\AutoModerator\Checks\Ai;

use Goodport\AiModerator\Audit\ModerationAuditContext;
use Goodport\AiModerator\Checks\PendingCheck;
use Goodport\AiModerator\Config\InvalidConfigurationException as AiInvalidConfigurationException;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Input\InvalidInputException as AiInvalidInputException;
use Goodport\AiModerator\Policies\PendingPolicy;
use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Contracts\ValidatesSettings;
use Goodport\AutoModerator\Exceptions\InvalidCheckResultException;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Support\InputFields;
use Throwable;

/**
 * Runs a ready AI policy or a single AI check.
 *
 * The adapter never rebuilds the AI pipeline. It forwards input, language, and
 * audit context, then maps the AI result or report into one check result.
 */
final class AiModerationCheck implements ModerationCheck, ValidatesSettings
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function validateSettings(string $check, array $settings): void
    {
        AiSettings::fromArray($settings, $check);
    }

    public function check(ModerationContext $context): CheckResult
    {
        $settings = AiSettings::fromArray($context->settings, $context->check);
        $language = $context->language ?? $settings->language;

        if ($language === null) {
            throw InvalidConfigurationException::missingLanguage($context->check);
        }

        try {
            return match ($settings->mode) {
                AiMode::Check => $this->runCheck($context, $settings, $language),
                AiMode::Policy => $this->runPolicy($context, $settings, $language),
            };
        } catch (AiInvalidConfigurationException $exception) {
            throw new InvalidConfigurationException(
                sprintf(
                    'AI %s "%s" is invalid: %s',
                    $settings->mode->value,
                    $settings->key,
                    $exception->getMessage(),
                ),
                0,
                $exception,
            );
        } catch (AiInvalidInputException $exception) {
            throw new InvalidInputException(
                sprintf('AI moderation input is invalid: %s', $exception->getMessage()),
                0,
                $exception,
            );
        } catch (InvalidConfigurationException|InvalidInputException|InvalidCheckResultException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return CheckResult::undecidedResult(
                $context->check,
                sprintf('AI moderation failed: %s', $exception->getMessage()),
                details: [
                    'mode' => $settings->mode->value,
                    'key' => $settings->key,
                ],
            );
        }
    }

    private function runCheck(ModerationContext $context, AiSettings $settings, string $language): CheckResult
    {
        $pending = AiModerator::check($settings->key)
            ->input($context->fields)
            ->language($language);

        $this->attachAudit($pending, $context, $settings->key);

        return AiReportMapper::mapResult($context->check, $settings->key, $pending->run());
    }

    private function runPolicy(ModerationContext $context, AiSettings $settings, string $language): CheckResult
    {
        $pending = AiModerator::policy($settings->key)
            ->input(InputFields::select($context, $settings->fields))
            ->language($language);

        $this->attachAudit($pending, $context, null);

        return AiReportMapper::mapReport($context->check, $settings->key, $pending->run());
    }

    private function attachAudit(
        PendingCheck|PendingPolicy $pending,
        ModerationContext $context,
        ?string $policy,
    ): void {
        if ($context->audit === null) {
            return;
        }

        $pending->audit(new ModerationAuditContext(
            policy: $policy,
            actorType: $context->audit->actorType,
            actorId: $context->audit->actorId,
            subjectType: $context->audit->subjectType,
            subjectId: $context->audit->subjectId,
            requestUrl: $context->audit->requestUrl,
        ));
    }
}
