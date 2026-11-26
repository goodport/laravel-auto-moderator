<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;

final class MismatchedKeyCheck implements ModerationCheck
{
    public function check(ModerationContext $context): CheckResult
    {
        return CheckResult::passedResult('another_check');
    }
}
