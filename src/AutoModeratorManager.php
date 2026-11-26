<?php

namespace Goodport\AutoModerator;

use Goodport\AutoModerator\Orchestration\ModerationRunner;
use Goodport\AutoModerator\Profiles\ProfileResolver;

/**
 * Entry point for the AutoModerator facade.
 */
final class AutoModeratorManager
{
    public function __construct(
        private readonly ProfileResolver $resolver,
        private readonly ModerationRunner $runner,
    ) {}

    public function profile(string $profile): PendingProfile
    {
        return new PendingProfile($profile, $this->resolver, $this->runner);
    }
}
