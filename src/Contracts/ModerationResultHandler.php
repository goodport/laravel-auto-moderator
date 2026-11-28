<?php

namespace Goodport\AutoModerator\Contracts;

use Goodport\AutoModerator\Moderation\ModerationOutcome;
use Illuminate\Database\Eloquent\Model;

/**
 * Application handler for a completed background moderation run.
 */
interface ModerationResultHandler
{
    public function handle(Model $model, ModerationOutcome $outcome): void;
}
