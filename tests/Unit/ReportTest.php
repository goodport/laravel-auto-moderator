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
        $this->assertSame([], $array['messages']);
        $this->assertSame([], $array['violations']);
        $this->assertSame([], $array['warnings']);
    }
}
