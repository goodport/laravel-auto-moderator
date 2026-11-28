<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Moderation\ModerationState;

final class EmptyReasonCheck implements ModerationCheck
{
    public function check(ModerationContext $context): CheckResult
    {
        return new CheckResult(
            check: $context->check,
            state: ModerationState::Undecided,
            reason: '',
            findings: [],
            details: [],
            error: 'Technical failure.',
        );
    }
}
