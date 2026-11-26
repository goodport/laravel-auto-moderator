<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;

final class RecordingCheck implements ModerationCheck
{
    /**
     * @var array<int, ModerationContext>
     */
    public static array $contexts = [];

    public static function reset(): void
    {
        self::$contexts = [];
    }

    public function check(ModerationContext $context): CheckResult
    {
        self::$contexts[] = $context;

        return CheckResult::passedResult($context->check);
    }
}
