<?php

namespace Goodport\AutoModerator\Moderation;

use Goodport\AutoModerator\Support\ModerationValue;

/**
 * Public outcome of one moderation case field.
 */
final readonly class ModerationFieldOutcome
{
    public const STATE_PASSED = 'passed';

    public const STATE_REJECTED = 'rejected';

    public const STATE_UNDECIDED = 'undecided';

    public const STATE_SKIPPED = 'skipped';

    /**
     * @param  array<string, mixed>  $details
     * @param  array<int, array<string, mixed>>  $findings
     * @param  array<string, string>  $reasonsByCheck
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $state,
        public ?string $reason,
        public ?string $error,
        public array $details,
        public array $findings,
        public array $reasonsByCheck,
    ) {}

    /**
     * Public field states.
     *
     * @return array<int, string>
     */
    public static function states(): array
    {
        return [
            self::STATE_PASSED,
            self::STATE_REJECTED,
            self::STATE_UNDECIDED,
            self::STATE_SKIPPED,
        ];
    }

    public function isPassed(): bool
    {
        return $this->state === self::STATE_PASSED;
    }

    public function isRejected(): bool
    {
        return $this->state === self::STATE_REJECTED;
    }

    public function isUndecided(): bool
    {
        return $this->state === self::STATE_UNDECIDED;
    }

    public function isSkipped(): bool
    {
        return $this->state === self::STATE_SKIPPED;
    }

    /**
     * A field needs review when its state is undecided, including a technical error.
     */
    public function needsReview(): bool
    {
        return $this->isUndecided();
    }

    public function hasTechnicalError(): bool
    {
        return ModerationValue::hasValue($this->error);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'state' => $this->state,
            'reason' => $this->reason,
            'error' => $this->error,
            'details' => $this->details,
            'findings' => $this->findings,
            'reasons_by_check' => $this->reasonsByCheck,
        ];
    }
}
