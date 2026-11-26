<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;

final class InconsistentRejectedCheck implements ModerationCheck
{
    public function check(ModerationContext $context): CheckResult
    {
        return CheckResult::rejectedResult($context->check, 'Rejected without findings.', []);
    }
}
