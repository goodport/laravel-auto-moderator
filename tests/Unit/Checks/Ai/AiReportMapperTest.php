<?php

namespace Goodport\AutoModerator\Tests\Unit\Checks\Ai;

use Goodport\AiModerator\Checks\Severity as AiSeverity;
use Goodport\AiModerator\Results\Report as AiReport;
use Goodport\AiModerator\Results\Result as AiResult;
use Goodport\AiModerator\Results\ResultMeta;
use Goodport\AiModerator\Results\ResultState as AiState;
use Goodport\AiModerator\Results\Violation;
use Goodport\AutoModerator\Checks\Ai\AiReportMapper;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationState;
use PHPUnit\Framework\TestCase;

final class AiReportMapperTest extends TestCase
{
    /**
     * A passed AI check with a skipped sibling is still a passed package check.
     */
    public function test_passed_and_skipped_is_passed(): void
    {
        $report = new AiReport([
            'body' => $this->aiResult('body', AiState::Passed),
            'title' => $this->aiResult('title', AiState::Skipped),
        ]);

        $result = AiReportMapper::mapReport('ai', 'post', $report);

        $this->assertSame(ModerationState::Passed, $result->state);
        $this->assertTrue($result->passed());
        $this->assertSame('passed', $result->details['checks']['body']['state']);
        $this->assertSame('skipped', $result->details['checks']['title']['state']);
    }

    /**
     * A single passed AI result maps to a passed check result.
     */
    public function test_maps_single_passed_result(): void
    {
        $result = AiReportMapper::mapResult(
            'ai_text_name',
            'text_name',
            $this->aiResult('text_name', AiState::Passed, 'Fine.'),
        );

        $this->assertSame(ModerationState::Passed, $result->state);
        $this->assertSame('text_name', $result->details['check']);
        $this->assertSame('passed', $result->details['result']['state']);
    }

    /**
     * A single rejected AI result keeps violations and severity.
     */
    public function test_maps_single_rejected_result(): void
    {
        $result = AiReportMapper::mapResult(
            'ai_text_name',
            'text_name',
            $this->aiResult(
                'text_name',
                AiState::Rejected,
                'Bad body.',
                [new Violation('bad', 'Bad body.', AiSeverity::High, true)],
            ),
        );

        $this->assertSame(ModerationState::Rejected, $result->state);
        $this->assertSame('Bad body.', $result->reason);
        $this->assertSame('bad', $result->findings[0]->code);
        $this->assertSame(FindingSeverity::High, $result->findings[0]->severity);
        $this->assertTrue($result->findings[0]->blocking);
        $this->assertSame('text_name', $result->details['check']);
    }

    /**
     * A single undecided AI result keeps its reason and error.
     */
    public function test_maps_single_undecided_result(): void
    {
        $result = AiReportMapper::mapResult(
            'ai_text_name',
            'text_name',
            $this->aiResult('text_name', AiState::Undecided, 'Broken.', [], 'Failure.'),
        );

        $this->assertSame(ModerationState::Undecided, $result->state);
        $this->assertSame('Broken.', $result->reason);
        $this->assertSame('Failure.', $result->error);
    }

    /**
     * A single skipped AI result maps to a skipped check result.
     */
    public function test_maps_single_skipped_result(): void
    {
        $result = AiReportMapper::mapResult(
            'ai_text_name',
            'text_name',
            $this->aiResult('text_name', AiState::Skipped),
        );

        $this->assertSame(ModerationState::Skipped, $result->state);
        $this->assertSame('text_name', $result->details['check']);
    }

    /**
     * @param  array<int, Violation>  $violations
     */
    private function aiResult(
        string $check,
        AiState $state,
        ?string $reason = null,
        array $violations = [],
        ?string $error = null,
    ): AiResult {
        return new AiResult(
            state: $state,
            check: $check,
            reason: $reason ?? ($state === AiState::Passed ? 'Fine.' : null),
            violations: $violations,
            label: null,
            answers: [],
            details: [],
            meta: new ResultMeta(
                check: $check,
                pass: 1,
                provider: 'openai',
                model: 'gpt-test',
                invocationId: 'invocation',
                durationMs: 10,
                error: $error,
                traceId: 'trace',
            ),
        );
    }
}
