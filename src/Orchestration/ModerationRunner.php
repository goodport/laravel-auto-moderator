<?php

namespace Goodport\AutoModerator\Orchestration;

use Goodport\AutoModerator\Exceptions\InvalidCheckResultException;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Moderation\ModerationState;
use Goodport\AutoModerator\Moderation\Report;
use Goodport\AutoModerator\Profiles\ResolvedCheck;
use Goodport\AutoModerator\Profiles\ResolvedProfile;
use LogicException;
use Throwable;

/**
 * Runs resolved checks in profile order and aggregates their results.
 */
final class ModerationRunner
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function run(ResolvedProfile $profile, array $fields, ?string $language, ?AuditContext $audit): Report
    {
        $results = [];

        foreach ($profile->checks as $resolvedCheck) {
            if (! $resolvedCheck->enabled) {
                $results[$resolvedCheck->key] = CheckResult::skippedResult($resolvedCheck->key);

                continue;
            }

            $result = $this->runCheck($resolvedCheck, $fields, $language, $audit);
            $results[$resolvedCheck->key] = $result;

            if ($profile->stopOnReject && $result->state === ModerationState::Rejected) {
                break;
            }
        }

        return new Report($results);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function runCheck(ResolvedCheck $resolvedCheck, array $fields, ?string $language, ?AuditContext $audit): CheckResult
    {
        $check = $resolvedCheck->instance;

        if ($check === null) {
            throw new LogicException('An enabled moderation check must have an instance.');
        }

        $checkLanguage = $language ?? ($resolvedCheck->settings['language'] ?? null);

        if ($checkLanguage !== null && (! is_string($checkLanguage) || trim($checkLanguage) === '')) {
            throw InvalidConfigurationException::invalidSetting(
                $resolvedCheck->key.'.language',
                'must be a non-empty string or null.',
            );
        }

        $context = new ModerationContext(
            check: $resolvedCheck->key,
            fields: $fields,
            language: $checkLanguage,
            settings: $resolvedCheck->settings,
            audit: $audit,
        );

        try {
            $result = $check->check($context);
        } catch (InvalidConfigurationException|InvalidInputException|InvalidCheckResultException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return CheckResult::undecidedResult($resolvedCheck->key, $exception->getMessage());
        }

        if ($result->check !== $resolvedCheck->key) {
            throw new InvalidCheckResultException(
                sprintf(
                    'Check "%s" returned a result for "%s".',
                    $resolvedCheck->key,
                    $result->check,
                ),
            );
        }

        return $result;
    }
}
