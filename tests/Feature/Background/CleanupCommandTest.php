<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Models\ModerationRun;
use Goodport\AutoModerator\Moderation\ModerationRunStatus;

final class CleanupCommandTest extends BackgroundTestCase
{
    /**
     * Only stale running runs are marked as failed.
     */
    public function test_marks_only_stale_running_runs_as_failed(): void
    {
        $stale = $this->createRun(ModerationRunStatus::Running, now()->subMinutes(61));
        $fresh = $this->createRun(ModerationRunStatus::Running, now()->subMinutes(30));
        $completed = $this->createRun(ModerationRunStatus::Approved, now()->subMinutes(90));

        $this->artisan('auto-moderator:cleanup')->assertSuccessful();

        $this->assertSame(ModerationRunStatus::Failed, $stale->fresh()->status);
        $this->assertNotNull($stale->fresh()->finished_at);
        $this->assertNotNull($stale->fresh()->reason);
        $this->assertSame(ModerationRunStatus::Running, $fresh->fresh()->status);
        $this->assertSame(ModerationRunStatus::Approved, $completed->fresh()->status);
    }

    private function createRun(ModerationRunStatus $status, \DateTimeInterface $startedAt): ModerationRun
    {
        $run = new ModerationRun;
        $run->moderatable_type = 'subject';
        $run->moderatable_id = '1';
        $run->scenario = 'subject_scenario';
        $run->status = $status;
        $run->started_at = $startedAt;
        $run->save();

        return $run;
    }
}
