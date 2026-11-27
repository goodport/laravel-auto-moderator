<?php

namespace Goodport\AutoModerator\Checks\Ai;

use Goodport\AiModerator\Checks\Severity as AiSeverity;
use Goodport\AiModerator\Results\Report as AiReport;
use Goodport\AiModerator\Results\Result as AiResult;
use Goodport\AiModerator\Results\ResultState as AiState;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationState;

/**
 * Converts AI results into package check results.
 *
 * The policy report treats a run where every check was skipped as passed. The
 * report mapper detects that case separately and returns skipped instead.
 */
final class AiReportMapper
{
    public static function mapReport(string $check, string $policy, AiReport $report): CheckResult
    {
        $results = $report->results();
        $state = self::state(array_values($results));
        $findings = self::findingsFromResults($results);
        $details = [
            'policy' => $policy,
            'checks' => array_map(
                fn (AiResult $result): array => self::resultDetails($result),
                $results,
            ),
        ];

        return match ($state) {
            ModerationState::Rejected => CheckResult::rejectedResult(
                check: $check,
                reason: self::reason($results, AiState::Rejected) ?? 'AI moderation rejected the content.',
                findings: $findings,
                details: $details,
            ),
            ModerationState::Undecided => CheckResult::undecidedResult(
                check: $check,
                error: self::error($results) ?? 'AI moderation could not decide.',
                reason: self::reason($results, AiState::Undecided),
                findings: $findings,
                details: $details,
            ),
            ModerationState::Skipped => CheckResult::skippedResult($check, details: $details),
            ModerationState::Passed => CheckResult::passedResult($check, $findings, $details),
        };
    }

    public static function mapResult(string $check, string $aiCheck, AiResult $result): CheckResult
    {
        $findings = self::findingsFromResult($aiCheck, $result);
        $details = [
            'check' => $aiCheck,
            'result' => self::resultDetails($result),
        ];

        return match ($result->state) {
            AiState::Rejected => CheckResult::rejectedResult(
                check: $check,
                reason: $result->reason ?? 'AI moderation rejected the content.',
                findings: $findings,
                details: $details,
            ),
            AiState::Undecided => CheckResult::undecidedResult(
                check: $check,
                error: $result->meta->error ?? $result->reason ?? 'AI moderation could not decide.',
                reason: $result->reason,
                findings: $findings,
                details: $details,
            ),
            AiState::Skipped => CheckResult::skippedResult($check, details: $details),
            AiState::Passed => CheckResult::passedResult($check, $findings, $details),
        };
    }

    /**
     * @param  array<int, AiResult>  $results
     */
    private static function state(array $results): ModerationState
    {
        $states = array_map(fn (AiResult $result): AiState => $result->state, $results);

        if (in_array(AiState::Rejected, $states, true)) {
            return ModerationState::Rejected;
        }

        if (in_array(AiState::Undecided, $states, true)) {
            return ModerationState::Undecided;
        }

        if ($states === []) {
            return ModerationState::Skipped;
        }

        foreach ($states as $state) {
            if ($state !== AiState::Skipped) {
                return ModerationState::Passed;
            }
        }

        return ModerationState::Skipped;
    }

    /**
     * @param  array<string, AiResult>  $results
     * @return array<int, Finding>
     */
    private static function findingsFromResults(array $results): array
    {
        $findings = [];

        foreach ($results as $check => $result) {
            $findings = array_merge($findings, self::findingsFromResult($check, $result));
        }

        return $findings;
    }

    /**
     * @return array<int, Finding>
     */
    private static function findingsFromResult(string $aiCheck, AiResult $result): array
    {
        $findings = [];

        foreach ($result->violations as $violation) {
            $findings[] = new Finding(
                code: $violation->code,
                message: $violation->message,
                severity: self::severity($violation->severity),
                blocking: $violation->blocking,
                details: ['check' => $aiCheck],
            );
        }

        return $findings;
    }

    private static function severity(AiSeverity $severity): FindingSeverity
    {
        return match ($severity) {
            AiSeverity::Low => FindingSeverity::Low,
            AiSeverity::Medium => FindingSeverity::Medium,
            AiSeverity::High => FindingSeverity::High,
        };
    }

    /**
     * @param  array<string, AiResult>  $results
     */
    private static function reason(array $results, AiState $state): ?string
    {
        foreach ($results as $result) {
            if ($result->state === $state && $result->reason !== null) {
                return $result->reason;
            }
        }

        return null;
    }

    /**
     * @param  array<string, AiResult>  $results
     */
    private static function error(array $results): ?string
    {
        foreach ($results as $result) {
            if ($result->state === AiState::Undecided && $result->meta->error !== null) {
                return $result->meta->error;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function resultDetails(AiResult $result): array
    {
        return [
            'state' => $result->state->value,
            'reason' => $result->reason,
            'label' => $result->label,
            'answers' => $result->answers,
            'details' => $result->details,
            'error' => $result->meta->error,
        ];
    }
}
