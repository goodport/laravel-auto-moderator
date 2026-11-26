<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationContext;

final class RejectingCheck implements ModerationCheck
{
    public function check(ModerationContext $context): CheckResult
    {
        return CheckResult::rejectedResult(
            $context->check,
            'Rejected by fixture.',
            [new Finding('fixture', 'Rejected by fixture.', FindingSeverity::High, true)],
        );
    }
}
