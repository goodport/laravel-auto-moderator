<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Jobs\RunModerationJob;
use Goodport\AutoModerator\Tests\Fixtures\ModeratableSubject;
use Goodport\AutoModerator\Tests\Fixtures\RecordingSource;
use Goodport\AutoModerator\Tests\Fixtures\TestSubject;
use Illuminate\Support\Facades\Queue;
use stdClass;

final class DispatchCommandTest extends BackgroundTestCase
{
    /**
     * The command queues one job for every pending model.
     */
    public function test_dispatches_job_for_each_pending_model(): void
    {
        Queue::fake();

        $first = $this->createSubject(fields: [], scenario: 'text_scenario');
        $second = $this->createSubject(fields: [], scenario: 'comment_scenario');
        RecordingSource::$pending = [$first, $second];
        $this->app['config']->set('auto-moderator.background.sources', [RecordingSource::class]);

        $this->artisan('auto-moderator:dispatch')->assertSuccessful();

        Queue::assertPushed(RunModerationJob::class, 2);
        Queue::assertPushed(RunModerationJob::class, fn (RunModerationJob $job): bool => $job->morphType === ModeratableSubject::class
            && $job->morphId === $first->getKey()
            && $job->scenario === 'text_scenario');
    }

    /**
     * The command uses the configured queue name.
     */
    public function test_uses_configured_queue(): void
    {
        Queue::fake();

        $subject = $this->createSubject(fields: []);
        RecordingSource::$pending = [$subject];
        $this->app['config']->set('auto-moderator.background.sources', [RecordingSource::class]);
        $this->app['config']->set('auto-moderator.background.queue', 'moderation');

        $this->artisan('auto-moderator:dispatch')->assertSuccessful();

        Queue::assertPushedOn('moderation', RunModerationJob::class);
    }

    /**
     * A duplicated entity and scenario is queued only once.
     */
    public function test_prevents_duplicate_jobs_for_same_entity_and_scenario(): void
    {
        Queue::fake();

        $subject = $this->createSubject(fields: []);
        RecordingSource::$pending = [$subject, $subject];
        $this->app['config']->set('auto-moderator.background.sources', [RecordingSource::class]);

        $this->artisan('auto-moderator:dispatch')->assertSuccessful();

        Queue::assertPushed(RunModerationJob::class, 1);
    }

    /**
     * A source that returns a model without the contract fails before dispatch.
     */
    public function test_invalid_source_model_throws_before_dispatch(): void
    {
        Queue::fake();

        $subject = new TestSubject;
        $subject->save();
        RecordingSource::$pending = [$subject];
        $this->app['config']->set('auto-moderator.background.sources', [RecordingSource::class]);

        $this->assertCommandFails();

        Queue::assertNothingPushed();
    }

    /**
     * A source class must implement the contract.
     */
    public function test_source_must_implement_contract(): void
    {
        Queue::fake();

        $this->app['config']->set('auto-moderator.background.sources', [stdClass::class]);

        $this->assertCommandFails();

        Queue::assertNothingPushed();
    }

    /**
     * The command does nothing without registered sources.
     */
    public function test_empty_sources_do_nothing(): void
    {
        Queue::fake();

        $this->app['config']->set('auto-moderator.background.sources', []);

        $this->artisan('auto-moderator:dispatch')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    private function assertCommandFails(): void
    {
        try {
            $this->artisan('auto-moderator:dispatch')->run();
        } catch (InvalidConfigurationException) {
            return;
        }

        $this->fail('The dispatch command must throw a configuration exception.');
    }
}
