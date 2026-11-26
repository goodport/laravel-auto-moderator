<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationContext;

final class WarningCheck implements ModerationCheck
{
    public function check(ModerationContext $context): CheckResult
    {
        return CheckResult::passedResult(
            $context->check,
            [new Finding('fixture_warning', 'A warning.', FindingSeverity::Low, false)],
        );
    }
}
