<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Result of one moderation case field.
 */
final readonly class ModerationFieldResult
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $skipped,
        public ?Report $report,
    ) {}

    public static function skipped(ModerationField $field): self
    {
        return new self($field->key, $field->label, true, null);
    }

    public static function checked(ModerationField $field, Report $report): self
    {
        return new self($field->key, $field->label, false, $report);
    }

    public function state(): ModerationState
    {
        return $this->report?->state() ?? ModerationState::Skipped;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'skipped' => $this->skipped,
            'state' => $this->state()->value,
            'report' => $this->report?->toArray(),
        ];
    }
}
