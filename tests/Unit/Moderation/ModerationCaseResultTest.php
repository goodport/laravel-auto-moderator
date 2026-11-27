<?php

namespace Goodport\AutoModerator\Tests\Unit\Moderation;

use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationCaseResult;
use Goodport\AutoModerator\Moderation\ModerationFieldResult;
use Goodport\AutoModerator\Moderation\ModerationState;
use Goodport\AutoModerator\Moderation\Report;
use PHPUnit\Framework\TestCase;

final class ModerationCaseResultTest extends TestCase
{
    /**
     * A rejection dominates an undecided field.
     */
    public function test_rejection_dominates_undecided(): void
    {
        $result = new ModerationCaseResult([
            'undecided' => $this->checked('undecided', $this->undecidedReport('Failure.')),
            'rejected' => $this->checked('rejected', $this->rejectedReport('Rejected.')),
        ]);

        $this->assertSame(ModerationState::Rejected, $result->state());
        $this->assertFalse($result->passed());
        $this->assertTrue($result->rejected());
    }

    /**
     * An undecided field is never reported as a pass.
     */
    public function test_undecided_is_not_passed(): void
    {
        $result = new ModerationCaseResult([
            'passed' => $this->checked('passed', $this->passedReport()),
            'undecided' => $this->checked('undecided', $this->undecidedReport('Failure.')),
        ]);

        $this->assertSame(ModerationState::Undecided, $result->state());
        $this->assertNull($result->passed());
        $this->assertTrue($result->undecided());
    }

    /**
     * A case where every field is skipped is skipped.
     */
    public function test_all_fields_skipped(): void
    {
        $result = new ModerationCaseResult([
            'first' => $this->skipped('first'),
            'second' => $this->skipped('second'),
        ]);

        $this->assertSame(ModerationState::Skipped, $result->state());
        $this->assertTrue($result->allSkipped());
        $this->assertNull($result->passed());
        $this->assertNull($result->firstReason());
    }

    /**
     * A case without fields is skipped.
     */
    public function test_empty_case_is_skipped(): void
    {
        $result = new ModerationCaseResult([]);

        $this->assertSame(ModerationState::Skipped, $result->state());
        $this->assertTrue($result->allSkipped());
        $this->assertNull($result->firstReason());
    }

    /**
     * The first reason prefers a rejection over an earlier undecided field.
     */
    public function test_first_reason_prefers_rejection(): void
    {
        $result = new ModerationCaseResult([
            'first' => $this->checked('first', $this->undecidedReport('Failure.')),
            'second' => $this->checked('second', $this->rejectedReport('Rejected.')),
        ]);

        $this->assertSame('Rejected.', $result->firstReason());
    }

    /**
     * The first reason falls back to an undecided field.
     */
    public function test_first_reason_uses_undecided_without_rejection(): void
    {
        $result = new ModerationCaseResult([
            'passed' => $this->checked('passed', $this->passedReport()),
            'undecided' => $this->checked('undecided', $this->undecidedReport('Failure.')),
        ]);

        $this->assertSame('Failure.', $result->firstReason());
    }

    /**
     * The array representation preserves field order, labels, and reports.
     */
    public function test_to_array_preserves_fields_and_reports(): void
    {
        $result = new ModerationCaseResult([
            'body' => new ModerationFieldResult('body', 'Body', false, $this->passedReport()),
            'comment' => new ModerationFieldResult('comment', 'Comment', true, null),
        ]);

        $array = $result->toArray();

        $this->assertSame('passed', $array['state']);
        $this->assertTrue($array['passed']);
        $this->assertSame(['body', 'comment'], array_keys($array['fields']));
        $this->assertSame('Body', $array['fields']['body']['label']);
        $this->assertFalse($array['fields']['body']['skipped']);
        $this->assertSame('passed', $array['fields']['body']['state']);
        $this->assertSame('passed', $array['fields']['body']['report']['state']);
        $this->assertSame('Comment', $array['fields']['comment']['label']);
        $this->assertTrue($array['fields']['comment']['skipped']);
        $this->assertSame('skipped', $array['fields']['comment']['state']);
        $this->assertNull($array['fields']['comment']['report']);
    }

    private function checked(string $key, Report $report): ModerationFieldResult
    {
        return new ModerationFieldResult($key, ucfirst($key), false, $report);
    }

    private function skipped(string $key): ModerationFieldResult
    {
        return new ModerationFieldResult($key, ucfirst($key), true, null);
    }

    private function passedReport(): Report
    {
        return new Report([
            'check' => CheckResult::passedResult('check'),
        ]);
    }

    private function undecidedReport(string $error): Report
    {
        return new Report([
            'check' => CheckResult::undecidedResult('check', $error),
        ]);
    }

    private function rejectedReport(string $reason): Report
    {
        return new Report([
            'check' => CheckResult::rejectedResult(
                'check',
                $reason,
                [new Finding('code', 'Message.', FindingSeverity::High, true)],
            ),
        ]);
    }
}
