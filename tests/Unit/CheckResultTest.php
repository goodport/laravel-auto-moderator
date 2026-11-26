<?php

namespace Goodport\AutoModerator\Tests\Unit;

use Goodport\AutoModerator\Exceptions\InvalidCheckResultException;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationState;
use PHPUnit\Framework\TestCase;

final class CheckResultTest extends TestCase
{
    /**
     * A rejected result must contain a blocking finding.
     */
    public function test_rejected_requires_blocking_finding(): void
    {
        $this->expectException(InvalidCheckResultException::class);

        new CheckResult(
            'check',
            ModerationState::Rejected,
            'Rejected.',
            [],
            [],
            null,
        );
    }

    /**
     * A passed result cannot contain a blocking finding.
     */
    public function test_passed_cannot_have_blocking_finding(): void
    {
        $this->expectException(InvalidCheckResultException::class);

        new CheckResult(
            'check',
            ModerationState::Passed,
            null,
            [new Finding('code', 'Message.', FindingSeverity::High, true)],
            [],
            null,
        );
    }

    /**
     * Only a rejected result may contain a blocking finding.
     */
    public function test_undecided_cannot_have_blocking_finding(): void
    {
        $this->expectException(InvalidCheckResultException::class);

        CheckResult::undecidedResult(
            'check',
            'Failure.',
            findings: [new Finding('code', 'Message.', FindingSeverity::High, true)],
        );
    }

    /**
     * A passed result may contain warnings.
     */
    public function test_passed_can_have_warning(): void
    {
        $result = CheckResult::passedResult(
            'check',
            [new Finding('code', 'Message.', FindingSeverity::Low, false)],
        );

        $this->assertTrue($result->passed());
        $this->assertCount(1, $result->findings);
    }

    /**
     * The array representation contains the state and findings.
     */
    public function test_to_array(): void
    {
        $result = CheckResult::rejectedResult(
            'check',
            'Rejected.',
            [new Finding('code', 'Message.', FindingSeverity::High, true, ['field' => 'body'])],
            ['matched' => 1],
        );

        $array = $result->toArray();

        $this->assertSame('check', $array['check']);
        $this->assertSame('rejected', $array['state']);
        $this->assertFalse($array['passed']);
        $this->assertSame('Rejected.', $array['reason']);
        $this->assertSame('code', $array['findings'][0]['code']);
        $this->assertSame(['field' => 'body'], $array['findings'][0]['details']);
        $this->assertSame(['matched' => 1], $array['details']);
    }
}
