<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Lifecycle status of a background moderation run.
 *
 * @internal
 */
enum ModerationRunStatus: string
{
    case Running = 'running';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NeedsReview = 'needs_review';
    case Skipped = 'skipped';
    case Failed = 'failed';

    /**
     * Map a moderation case result to the final run status.
     *
     * A technical check error is represented by the undecided case state.
     */
    public static function fromCaseResult(ModerationCaseResult $result): self
    {
        return match ($result->state()) {
            ModerationState::Rejected => self::Rejected,
            ModerationState::Undecided => self::NeedsReview,
            ModerationState::Passed => self::Approved,
            ModerationState::Skipped => self::Skipped,
        };
    }
}
