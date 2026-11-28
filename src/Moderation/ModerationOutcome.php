<?php

namespace Goodport\AutoModerator\Moderation;

use Goodport\AutoModerator\Support\ModerationValue;

/**
 * Public outcome of a background moderation run.
 */
final readonly class ModerationOutcome
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, ModerationFieldOutcome>  $fields
     */
    public function __construct(
        public string $scenario,
        public string $status,
        public array $fields,
    ) {}

    /**
     * Public run statuses.
     *
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_RUNNING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_NEEDS_REVIEW,
            self::STATUS_SKIPPED,
            self::STATUS_FAILED,
        ];
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function needsReview(): bool
    {
        return $this->status === self::STATUS_NEEDS_REVIEW;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * @return array<string, ModerationFieldOutcome>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $key): ?ModerationFieldOutcome
    {
        return $this->fields[$key] ?? null;
    }

    /**
     * A field failed when it was rejected, undecided, or had a technical error.
     */
    public function failed(string $key): bool
    {
        $field = $this->field($key);

        return $field !== null
            && ($field->isRejected() || $field->isUndecided() || $field->hasTechnicalError());
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function failedAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->failed($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ready-to-use reasons for rejected, undecided, and failed fields.
     *
     * @param  array<int, string>  $keys
     * @return array<string, string>
     */
    public function reasonsForFields(array $keys): array
    {
        $reasons = [];

        foreach ($keys as $key) {
            $field = $this->field($key);

            if ($field === null || $field->isPassed() || $field->isSkipped()) {
                continue;
            }

            $reason = ModerationValue::firstNonEmpty($field->reason, $field->error);

            if ($reason === null) {
                continue;
            }

            $reasons[$key] = $field->label.': '.$reason;
        }

        return $reasons;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario' => $this->scenario,
            'status' => $this->status,
            'fields' => array_map(
                fn (ModerationFieldOutcome $field): array => $field->toArray(),
                $this->fields,
            ),
        ];
    }
}
