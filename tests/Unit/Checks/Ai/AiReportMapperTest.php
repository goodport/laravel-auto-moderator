<?php

namespace Goodport\AutoModerator\Tests\Unit\Checks\Ai;

use Goodport\AiModerator\Results\Report as AiReport;
use Goodport\AiModerator\Results\Result as AiResult;
use Goodport\AiModerator\Results\ResultMeta;
use Goodport\AiModerator\Results\ResultState as AiState;
use Goodport\AutoModerator\Checks\Ai\AiReportMapper;
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

        $result = AiReportMapper::map('ai', 'post', $report);

        $this->assertSame(ModerationState::Passed, $result->state);
        $this->assertTrue($result->passed());
        $this->assertSame('passed', $result->details['checks']['body']['state']);
        $this->assertSame('skipped', $result->details['checks']['title']['state']);
    }

    private function aiResult(string $check, AiState $state): AiResult
    {
        return new AiResult(
            state: $state,
            check: $check,
            reason: $state === AiState::Passed ? 'Fine.' : null,
            violations: [],
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
                error: null,
                traceId: 'trace',
            ),
        );
    }
}
