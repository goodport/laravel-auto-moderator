<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Final state of a check, profile, or moderation case.
 */
enum ModerationState: string
{
    case Passed = 'passed';
    case Rejected = 'rejected';
    case Undecided = 'undecided';
    case Skipped = 'skipped';

    /**
     * Aggregate check or field states by priority.
     *
     * @param  array<int, self>  $states
     */
    public static function aggregate(array $states): self
    {
        if (in_array(self::Rejected, $states, true)) {
            return self::Rejected;
        }

        if (in_array(self::Undecided, $states, true)) {
            return self::Undecided;
        }

        if (in_array(self::Passed, $states, true)) {
            return self::Passed;
        }

        return self::Skipped;
    }
}
