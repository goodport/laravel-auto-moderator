<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Aggregated result of a moderation case.
 */
final readonly class ModerationCaseResult
{
    /**
     * @param  array<string, ModerationFieldResult>  $results
     */
    public function __construct(public array $results) {}

    public function state(): ModerationState
    {
        return ModerationState::aggregate(array_map(
            fn (ModerationFieldResult $result): ModerationState => $result->state(),
            array_values($this->results),
        ));
    }

    public function passed(): ?bool
    {
        return match ($this->state()) {
            ModerationState::Passed => true,
            ModerationState::Rejected => false,
            ModerationState::Undecided, ModerationState::Skipped => null,
        };
    }

    public function rejected(): bool
    {
        return $this->state() === ModerationState::Rejected;
    }

    public function undecided(): bool
    {
        return $this->state() === ModerationState::Undecided;
    }

    public function allSkipped(): bool
    {
        return $this->state() === ModerationState::Skipped;
    }

    /**
     * @return array<string, ModerationFieldResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function result(string $key): ?ModerationFieldResult
    {
        return $this->results[$key] ?? null;
    }

    /**
     * The first rejection reason, then the first undecided reason.
     */
    public function firstReason(): ?string
    {
        return $this->firstReasonForState(ModerationState::Rejected)
            ?? $this->firstReasonForState(ModerationState::Undecided);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state()->value,
            'passed' => $this->passed(),
            'rejected' => $this->rejected(),
            'undecided' => $this->undecided(),
            'all_skipped' => $this->allSkipped(),
            'first_reason' => $this->firstReason(),
            'fields' => array_map(
                fn (ModerationFieldResult $result): array => $result->toArray(),
                $this->results,
            ),
        ];
    }

    private function firstReasonForState(ModerationState $state): ?string
    {
        foreach ($this->results as $result) {
            if ($result->state() !== $state) {
                continue;
            }

            $reason = $result->report?->firstReason();

            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }
}
