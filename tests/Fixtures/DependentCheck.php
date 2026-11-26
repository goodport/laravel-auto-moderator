<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;

final class DependentCheck implements ModerationCheck
{
    public function __construct(private readonly DependentService $service) {}

    public function check(ModerationContext $context): CheckResult
    {
        return CheckResult::passedResult($context->check, details: ['service' => $this->service->name]);
    }
}
