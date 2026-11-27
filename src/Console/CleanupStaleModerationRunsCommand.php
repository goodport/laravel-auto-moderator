<?php

namespace Goodport\AutoModerator\Console;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Models\ModerationRun;
use Goodport\AutoModerator\Moderation\ModerationRunStatus;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;

/**
 * Marks background runs that stayed running for too long as failed.
 */
final class CleanupStaleModerationRunsCommand extends Command
{
    protected $signature = 'auto-moderator:cleanup';

    protected $description = 'Mark stale background moderation runs as failed.';

    public function handle(Repository $config): int
    {
        $staleAfterMinutes = $config->get('auto-moderator.background.stale_after_minutes', 60);

        if (! is_int($staleAfterMinutes) || $staleAfterMinutes < 1) {
            throw InvalidConfigurationException::invalidSetting(
                'background.stale_after_minutes',
                'must be a positive integer.',
            );
        }

        $updated = ModerationRun::query()
            ->where('status', ModerationRunStatus::Running->value)
            ->where('started_at', '<', now()->subMinutes($staleAfterMinutes))
            ->update([
                'status' => ModerationRunStatus::Failed->value,
                'reason' => 'The moderation run did not finish in time.',
                'finished_at' => now(),
            ]);

        $this->info("Marked {$updated} stale moderation runs as failed.");

        return self::SUCCESS;
    }
}
