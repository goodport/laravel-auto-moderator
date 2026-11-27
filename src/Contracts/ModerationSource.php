<?php

namespace Goodport\AutoModerator\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for a background moderation source.
 */
interface ModerationSource
{
    /**
     * Models that should be queued for moderation.
     *
     * @return iterable<int, Model&Moderatable>
     */
    public function pending(): iterable;
}
