<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;
use RuntimeException;

final class ThrowingCheck implements ModerationCheck
{
    public function check(ModerationContext $context): CheckResult
    {
        throw new RuntimeException('Fixture failure.');
    }
}
