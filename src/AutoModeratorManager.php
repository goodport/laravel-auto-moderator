<?php

namespace Goodport\AutoModerator;

use Goodport\AutoModerator\Orchestration\ModerationCaseRunner;
use Goodport\AutoModerator\Orchestration\ModerationRunner;
use Goodport\AutoModerator\Orchestration\RunContextFactory;
use Goodport\AutoModerator\Profiles\ProfileResolver;

/**
 * Entry point for the AutoModerator facade.
 */
final class AutoModeratorManager
{
    public function __construct(
        private readonly ProfileResolver $resolver,
        private readonly ModerationRunner $runner,
        private readonly RunContextFactory $contextFactory,
        private readonly ModerationCaseRunner $caseRunner,
    ) {}

    public function profile(string $profile): PendingProfile
    {
        return new PendingProfile($profile, $this->resolver, $this->runner, $this->contextFactory);
    }

    public function moderationCase(): PendingModerationCase
    {
        return new PendingModerationCase($this->caseRunner);
    }
}
