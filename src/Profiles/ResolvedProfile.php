<?php

namespace Goodport\AutoModerator\Profiles;

/**
 * A profile resolved into an ordered list of checks.
 */
final readonly class ResolvedProfile
{
    /**
     * @param  array<int, ResolvedCheck>  $checks
     */
    public function __construct(
        public string $key,
        public bool $enabled,
        public bool $stopOnReject,
        public array $checks,
    ) {}
}
