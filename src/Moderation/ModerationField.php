<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * One field of a moderation case.
 */
final readonly class ModerationField
{
    /**
     * @param  array<string, mixed>  $input  Named input fields for the profile.
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $profile,
        public array $input,
    ) {}

    /**
     * A field is empty when every input value is empty.
     */
    public function isEmpty(): bool
    {
        foreach ($this->input as $value) {
            if (! $this->isEmptyValue($value)) {
                return false;
            }
        }

        return true;
    }

    private function isEmptyValue(mixed $value): bool
    {
        return match (true) {
            $value === null => true,
            is_string($value) => trim($value) === '',
            is_array($value) => $value === [],
            default => false,
        };
    }
}
