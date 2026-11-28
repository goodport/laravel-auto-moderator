<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Jobs\RunModerationJob;
use Goodport\AutoModerator\Models\ModerationRun;
use Goodport\AutoModerator\Tests\Fixtures\ModeratableSubject;
use Goodport\AutoModerator\Tests\Fixtures\TestSubject;
use Illuminate\Support\Facades\Queue;

final class DispatchApiTest extends BackgroundTestCase
{
    /**
     * dispatch queues a unique job with the configured queue and delay.
     */
    public function test_dispatch_queues_job_with_delay_and_queue(): void
    {
        Queue::fake();
        $this->app['config']->set('auto-moderator.background.queue', 'moderation');
        $subject = $this->createSubject(fields: []);

        AutoModerator::dispatch($subject, 30);

        Queue::assertPushedOn('moderation', RunModerationJob::class);
        Queue::assertPushed(RunModerationJob::class, function (RunModerationJob $job) use ($subject): bool {
            return $job->scenario === 'subject_scenario'
                && $job->morphType === ModeratableSubject::class
                && (string) $job->morphId === (string) $subject->id
                && $job->delay === 30;
        });
        $this->assertSame(0, ModerationRun::query()->count());
    }

    /**
     * dispatch without a delay uses the default queue.
     */
    public function test_dispatch_without_delay_uses_default_queue(): void
    {
        Queue::fake();
        $subject = $this->createSubject(fields: []);

        AutoModerator::dispatch($subject);

        Queue::assertPushed(RunModerationJob::class, function (RunModerationJob $job): bool {
            return $job->delay === null && $job->queue === null;
        });
    }

    /**
     * A negative delay is an invalid argument.
     */
    public function test_dispatch_rejects_negative_delay(): void
    {
        Queue::fake();
        $subject = $this->createSubject(fields: []);

        $this->assertInvalidArgument(fn () => AutoModerator::dispatch($subject, -1));

        Queue::assertNothingPushed();
    }

    /**
     * dispatch rejects unpersisted, non-moderatable, and empty-scenario models.
     */
    public function test_dispatch_rejects_invalid_models_and_scenario(): void
    {
        Queue::fake();

        $this->assertInvalidArgument(fn () => AutoModerator::dispatch(new ModeratableSubject));

        $plain = new TestSubject;
        $plain->save();
        $this->assertInvalidArgument(fn () => AutoModerator::dispatch($plain));

        $subject = $this->createSubject(fields: [], scenario: '');
        $this->assertInvalidArgument(fn () => AutoModerator::dispatch($subject));

        Queue::assertNothingPushed();
    }
}
