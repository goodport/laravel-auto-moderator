<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * One violation or warning produced by a check.
 */
final readonly class Finding
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $code,
        public string $message,
        public FindingSeverity $severity,
        public bool $blocking,
        public array $details = [],
    ) {}

    /**
     * @return array{
     *     code: string,
     *     message: string,
     *     severity: string,
     *     blocking: bool,
     *     details: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'severity' => $this->severity->value,
            'blocking' => $this->blocking,
            'details' => $this->details,
        ];
    }
}
