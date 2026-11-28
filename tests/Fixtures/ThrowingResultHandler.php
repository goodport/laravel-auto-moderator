<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationResultHandler;
use Goodport\AutoModerator\Moderation\ModerationOutcome;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ThrowingResultHandler implements ModerationResultHandler
{
    public function handle(Model $model, ModerationOutcome $outcome): void
    {
        throw new RuntimeException('Handler failure.');
    }
}
