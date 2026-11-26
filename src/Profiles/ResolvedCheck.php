<?php

namespace Goodport\AutoModerator\Profiles;

use Goodport\AutoModerator\Contracts\ModerationCheck;

/**
 * One check resolved for a profile.
 */
final readonly class ResolvedCheck
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $key,
        public bool $enabled,
        public ?ModerationCheck $instance,
        public array $settings,
    ) {}
}
