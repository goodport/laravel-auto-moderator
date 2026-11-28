<?php

namespace Goodport\AutoModerator\Events;

use Goodport\AutoModerator\Models\ModerationRun;

/**
 * Published after a background moderation run is stored.
 *
 * @internal
 */
final readonly class ModerationCompleted
{
    public function __construct(public ModerationRun $run) {}
}
