<?php

namespace Goodport\AutoModerator\Tests\Unit;

use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationState;
use Goodport\AutoModerator\Moderation\Report;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    /**
     * A rejection dominates an undecided check.
     */
    public function test_rejected_dominates_undecided(): void
    {
        $report = new Report([
            'undecided' => CheckResult::undecidedResult('undecided', 'Failure.'),
            'rejected' => CheckResult::rejectedResult(
                'rejected',
                'Rejected.',
                [new Finding('code', 'Message.', FindingSeverity::High, true)],
            ),
        ]);

        $this->assertSame(ModerationState::Rejected, $report->state());
        $this->assertFalse($report->passed());
        $this->assertTrue($report->rejected());
    }

    /**
     * An undecided check dominates a passing check.
     */
    public function test_undecided_dominates_passed(): void
    {
        $report = new Report([
            'passed' => CheckResult::passedResult('passed'),
            'undecided' => CheckResult::undecidedResult('undecided', 'Failure.'),
        ]);

        $this->assertSame(ModerationState::Undecided, $report->state());
        $this->assertNull($report->passed());
    }

    /**
     * A report where every check is skipped is skipped.
     */
    public function test_all_skipped(): void
    {
        $report = new Report([
            'first' => CheckResult::skippedResult('first'),
            'second' => CheckResult::skippedResult('second'),
        ]);

        $this->assertSame(ModerationState::Skipped, $report->state());
        $this->assertTrue($report->allSkipped());
        $this->assertNull($report->passed());
        $this->assertFalse($report->rejected());
    }

    /**
     * An empty report is skipped.
     */
    public function test_empty_report_is_skipped(): void
    {
        $report = new Report([]);

        $this->assertSame(ModerationState::Skipped, $report->state());
        $this->assertTrue($report->allSkipped());
    }

    /**
     * Warnings are separated from blocking violations.
     */
    public function test_warnings_are_separated_from_violations(): void
    {
        $report = new Report([
            'passed' => CheckResult::passedResult(
                'passed',
                [new Finding('warning', 'Warning.', FindingSeverity::Low, false)],
            ),
        ]);

        $this->assertTrue($report->passed());
        $this->assertSame([], $report->violations());
        $this->assertArrayHasKey('passed', $report->warnings());
    }

    /**
     * The first reason ignores passed and skipped checks.
     */
    public function test_first_reason_ignores_passed_and_skipped(): void
    {
        $report = new Report([
            'passed' => CheckResult::passedResult('passed'),
            'skipped' => CheckResult::skippedResult('skipped', 'Not enough text.'),
            'undecided' => CheckResult::undecidedResult('undecided', 'Failure.'),
            'rejected' => CheckResult::rejectedResult(
                'rejected',
                'Rejected.',
                [new Finding('code', 'Message.', FindingSeverity::High, true)],
            ),
        ]);

        $this->assertSame('Failure.', $report->firstReason());
    }

    /**
     * The first reason returns a rejection reason.
     */
    public function test_first_reason_returns_rejection_reason(): void
    {
        $report = new Report([
            'rejected' => CheckResult::rejectedResult(
                'rejected',
                'Rejected.',
                [new Finding('code', 'Message.', FindingSeverity::High, true)],
            ),
        ]);

        $this->assertSame('Rejected.', $report->firstReason());
    }

    /**
     * The first reason is null when every check passed or was skipped.
     */
    public function test_first_reason_is_null_without_rejected_or_undecided(): void
    {
        $report = new Report([
            'passed' => CheckResult::passedResult('passed'),
            'skipped' => CheckResult::skippedResult('skipped', 'Not enough text.'),
        ]);

        $this->assertNull($report->firstReason());
    }

    /**
     * An undecided error is returned when there is no reason.
     */
    public function test_first_reason_uses_undecided_error(): void
    {
        $report = new Report([
            'undecided' => CheckResult::undecidedResult('undecided', 'Failure.'),
        ]);

        $this->assertSame('Failure.', $report->firstReason());
    }

    /**
     * The array representation includes every report section.
     */
    public function test_to_array(): void
    {
        $report = new Report([
            'passed' => CheckResult::passedResult('passed'),
        ]);

        $array = $report->toArray();

        $this->assertSame('passed', $array['state']);
        $this->assertTrue($array['passed']);
        $this->assertFalse($array['rejected']);
        $this->assertFalse($array['undecided']);
        $this->assertFalse($array['all_skipped']);
        $this->assertArrayHasKey('passed', $array['results']);
        $this->assertNull($array['first_reason']);
        $this->assertSame([], $array['messages']);
        $this->assertSame([], $array['violations']);
        $this->assertSame([], $array['warnings']);
    }
}
