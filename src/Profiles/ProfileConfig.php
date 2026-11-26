<?php

namespace Goodport\AutoModerator\Profiles;

/**
 * Validated profile configuration.
 */
final readonly class ProfileConfig
{
    /**
     * @param  array<int, array{check: string, enabled: bool, settings: array<string, mixed>}>  $checks
     */
    public function __construct(
        public string $key,
        public bool $enabled,
        public bool $stopOnReject,
        public array $checks,
    ) {}
}
