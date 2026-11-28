<?php

namespace Goodport\AutoModerator\Moderation;

use Goodport\AutoModerator\Exceptions\InvalidCheckResultException;
use Goodport\AutoModerator\Support\ModerationValue;

/**
 * Result of one moderation check.
 */
final readonly class CheckResult
{
    /**
     * @param  array<int, Finding>  $findings
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $check,
        public ModerationState $state,
        public ?string $reason,
        public array $findings,
        public array $details,
        public ?string $error,
    ) {
        $this->guardConsistency();
    }

    /**
     * @param  array<int, Finding>  $findings
     * @param  array<string, mixed>  $details
     */
    public static function passedResult(string $check, array $findings = [], array $details = []): self
    {
        return new self($check, ModerationState::Passed, null, $findings, $details, null);
    }

    /**
     * @param  array<int, Finding>  $findings
     * @param  array<string, mixed>  $details
     */
    public static function rejectedResult(string $check, string $reason, array $findings, array $details = []): self
    {
        return new self($check, ModerationState::Rejected, $reason, $findings, $details, null);
    }

    /**
     * @param  array<int, Finding>  $findings
     * @param  array<string, mixed>  $details
     */
    public static function undecidedResult(string $check, string $error, ?string $reason = null, array $findings = [], array $details = []): self
    {
        return new self($check, ModerationState::Undecided, $reason, $findings, $details, $error);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function skippedResult(string $check, ?string $reason = null, array $details = []): self
    {
        return new self($check, ModerationState::Skipped, $reason, [], $details, null);
    }

    public function passed(): ?bool
    {
        return match ($this->state) {
            ModerationState::Passed => true,
            ModerationState::Rejected => false,
            ModerationState::Undecided, ModerationState::Skipped => null,
        };
    }

    public function rejected(): bool
    {
        return $this->state === ModerationState::Rejected;
    }

    public function undecided(): bool
    {
        return $this->state === ModerationState::Undecided;
    }

    public function skipped(): bool
    {
        return $this->state === ModerationState::Skipped;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'check' => $this->check,
            'state' => $this->state->value,
            'passed' => $this->passed(),
            'reason' => $this->reason,
            'findings' => array_map(fn (Finding $finding): array => $finding->toArray(), $this->findings),
            'details' => $this->details,
            'error' => $this->error,
        ];
    }

    private function guardConsistency(): void
    {
        $hasBlockingFinding = false;

        foreach ($this->findings as $finding) {
            if ($finding->blocking) {
                $hasBlockingFinding = true;
                break;
            }
        }

        if ($this->state === ModerationState::Rejected && ! $hasBlockingFinding) {
            throw new InvalidCheckResultException(
                sprintf('Check "%s" returned a rejected result without a blocking finding.', $this->check),
            );
        }

        if ($this->state !== ModerationState::Rejected && $hasBlockingFinding) {
            throw new InvalidCheckResultException(
                sprintf('Check "%s" returned a blocking finding with state "%s".', $this->check, $this->state->value),
            );
        }

        if ($this->state !== ModerationState::Undecided && ModerationValue::hasValue($this->error)) {
            throw new InvalidCheckResultException(
                sprintf('Check "%s" returned an error with state "%s".', $this->check, $this->state->value),
            );
        }
    }
}
