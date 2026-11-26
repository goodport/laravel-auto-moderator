<?php

namespace Goodport\AutoModerator\Contracts;

use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;

/**
 * Contract for one moderation check.
 */
interface ModerationCheck
{
    /**
     * Run the check against the supplied context.
     */
    public function check(ModerationContext $context): CheckResult;
}
