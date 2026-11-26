<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Final state of a check or a profile.
 */
enum ModerationState: string
{
    case Passed = 'passed';
    case Rejected = 'rejected';
    case Undecided = 'undecided';
    case Skipped = 'skipped';
}
