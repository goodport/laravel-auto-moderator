<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Aggregated result of a moderation profile.
 */
final readonly class Report
{
    /**
     * @param  array<string, CheckResult>  $results
     */
    public function __construct(public array $results) {}

    public function state(): ModerationState
    {
        return ModerationState::aggregate(array_map(
            fn (CheckResult $result): ModerationState => $result->state,
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
     * @return array<string, CheckResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function result(string $check): ?CheckResult
    {
        return $this->results[$check] ?? null;
    }

    /**
     * The first reason or error from a rejected or undecided check.
     */
    public function firstReason(): ?string
    {
        foreach ($this->results as $result) {
            if ($result->state !== ModerationState::Rejected && $result->state !== ModerationState::Undecided) {
                continue;
            }

            $reason = $result->reason ?? $result->error;

            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * Reasons and errors for checks that did not pass.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->results as $check => $result) {
            if ($result->state === ModerationState::Passed) {
                continue;
            }

            $message = $result->reason ?? $result->error;

            if ($message !== null) {
                $messages[$check] = $message;
            }
        }

        return $messages;
    }

    /**
     * Blocking findings grouped by check.
     *
     * @return array<string, array<int, Finding>>
     */
    public function violations(): array
    {
        return $this->findingsByBlocking(true);
    }

    /**
     * Non-blocking findings grouped by check.
     *
     * @return array<string, array<int, Finding>>
     */
    public function warnings(): array
    {
        return $this->findingsByBlocking(false);
    }

    /**
     * All findings grouped by check.
     *
     * @return array<string, array<int, Finding>>
     */
    public function findings(): array
    {
        $findings = [];

        foreach ($this->results as $check => $result) {
            if ($result->findings !== []) {
                $findings[$check] = $result->findings;
            }
        }

        return $findings;
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
            'results' => array_map(fn (CheckResult $result): array => $result->toArray(), $this->results),
            'first_reason' => $this->firstReason(),
            'messages' => $this->messages(),
            'violations' => $this->findingsToArray($this->violations()),
            'warnings' => $this->findingsToArray($this->warnings()),
        ];
    }

    /**
     * @return array<string, array<int, Finding>>
     */
    private function findingsByBlocking(bool $blocking): array
    {
        $findings = [];

        foreach ($this->results as $check => $result) {
            $filtered = array_values(array_filter(
                $result->findings,
                fn (Finding $finding): bool => $finding->blocking === $blocking,
            ));

            if ($filtered !== []) {
                $findings[$check] = $filtered;
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, array<int, Finding>>  $findings
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function findingsToArray(array $findings): array
    {
        return array_map(
            fn (array $checkFindings): array => array_map(
                fn (Finding $finding): array => $finding->toArray(),
                $checkFindings,
            ),
            $findings,
        );
    }
}
