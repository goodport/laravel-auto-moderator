<?php

namespace Goodport\AutoModerator\Checks\Ai;

use Goodport\AiModerator\Audit\ModerationAuditContext;
use Goodport\AiModerator\Config\InvalidConfigurationException as AiInvalidConfigurationException;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Input\InvalidInputException as AiInvalidInputException;
use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Contracts\ValidatesSettings;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Support\InputFields;
use Throwable;

/**
 * Runs a ready policy from the AI moderator package.
 *
 * The adapter never rebuilds the AI pipeline. It forwards the declared input
 * fields, the resolved language, and the audit context, then maps the report.
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

        $pending = AiModerator::policy($settings->policy)
            ->input(InputFields::select($context, $settings->fields))
            ->language($language);

        if ($context->audit !== null) {
            $pending->audit(new ModerationAuditContext(
                actorType: $context->audit->actorType,
                actorId: $context->audit->actorId,
                subjectType: $context->audit->subjectType,
                subjectId: $context->audit->subjectId,
                requestUrl: $context->audit->requestUrl,
            ));
        }

        try {
            $report = $pending->run();
        } catch (AiInvalidConfigurationException $exception) {
            throw new InvalidConfigurationException(
                sprintf('AI policy "%s" is invalid: %s', $settings->policy, $exception->getMessage()),
                0,
                $exception,
            );
        } catch (AiInvalidInputException $exception) {
            throw new InvalidInputException(
                sprintf('AI moderation input is invalid: %s', $exception->getMessage()),
                0,
                $exception,
            );
        } catch (Throwable $exception) {
            return CheckResult::undecidedResult(
                $context->check,
                sprintf('AI moderation failed: %s', $exception->getMessage()),
                details: ['policy' => $settings->policy],
            );
        }

        return AiReportMapper::map($context->check, $settings->policy, $report);
    }
}
