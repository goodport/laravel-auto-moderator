<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\AutoModeratorManager;
use Goodport\AutoModerator\Jobs\RunModerationJob;
use Goodport\AutoModerator\Tests\Fixtures\ModeratableSubject;
use Goodport\AutoModerator\Tests\Fixtures\RecordingSource;
use Goodport\AutoModerator\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class BackgroundTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingSource::reset();
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../../Fixtures/database/migrations');
    }

    protected function registerProfile(string $name, string $check): void
    {
        $this->setCheck($name, $check);
        $this->setProfile($name, ['checks' => [['check' => $name]]]);
    }

    /**
     * @param  array<int, array{key: string, label: string, profile: string, input: array<string, mixed>}>  $fields
     */
    protected function createSubject(
        array $fields,
        string $scenario = 'subject_scenario',
        ?string $language = null,
        ?int $actorId = null,
    ): ModeratableSubject {
        $subject = new ModeratableSubject;
        $subject->scenario = $scenario;
        $subject->fields = $fields;
        $subject->language = $language;
        $subject->actor_id = $actorId;
        $subject->save();

        return $subject;
    }

    protected function handle(RunModerationJob $job): void
    {
        $job->handle($this->app->make(AutoModeratorManager::class));
    }
}
