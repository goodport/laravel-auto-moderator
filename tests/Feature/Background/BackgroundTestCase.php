<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Jobs\RunModerationJob;
use Goodport\AutoModerator\Models\ModerationResult;
use Goodport\AutoModerator\Models\ModerationRun;
use Goodport\AutoModerator\Moderation\ModerationOutcome;
use Goodport\AutoModerator\Moderation\ModerationRunStatus;
use Goodport\AutoModerator\Moderation\ModerationState;
use Goodport\AutoModerator\Tests\Fixtures\ModeratableSubject;
use Goodport\AutoModerator\Tests\Fixtures\RecordingResultHandler;
use Goodport\AutoModerator\Tests\Fixtures\RecordingSource;
use Goodport\AutoModerator\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

abstract class BackgroundTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingSource::reset();
        RecordingResultHandler::reset();
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
        $this->app->call([$job, 'handle']);
    }

    protected function moderationJob(ModeratableSubject $subject, ?string $scenario = null): RunModerationJob
    {
        return new RunModerationJob(
            morphType: $subject->getMorphClass(),
            morphId: $subject->getKey(),
            scenario: $scenario ?? $subject->moderationScenario(),
        );
    }

    /**
     * @return array{key: string, label: string, profile: string, input: array<string, mixed>}
     */
    protected function moderationField(string $key, string $label, string $profile, string $text): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'profile' => $profile,
            'input' => ['text' => $text],
        ];
    }

    protected function latestOutcome(ModeratableSubject $subject, ?string $scenario = null): ?ModerationOutcome
    {
        return AutoModerator::latest($subject, $scenario);
    }

    protected function assertJobFails(RunModerationJob $job): void
    {
        try {
            $this->handle($job);
        } catch (InvalidConfigurationException) {
            return;
        }

        $this->fail('The moderation job must throw a configuration exception.');
    }

    protected function assertInvalidArgument(callable $callback): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('The public moderation method must throw an invalid argument exception.');
    }

    /**
     * @param  array<string, string>|null  $fieldLabels
     */
    protected function seedRun(
        ModeratableSubject $subject,
        ModerationRunStatus $status,
        string $scenario = 'subject_scenario',
        ?array $fieldLabels = null,
    ): ModerationRun {
        $run = new ModerationRun;
        $run->moderatable_type = $subject->getMorphClass();
        $run->moderatable_id = (string) $subject->getKey();
        $run->scenario = $scenario;
        $run->status = $status;
        $run->field_labels = $fieldLabels;
        $run->started_at = now();
        $run->save();

        return $run;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<int, array<string, mixed>>  $findings
     */
    protected function seedResult(
        ModerationRun $run,
        string $fieldKey,
        ModerationState $state = ModerationState::Passed,
        ?string $reason = null,
        ?string $error = null,
        array $details = [],
        array $findings = [],
        string $checkKey = 'check',
    ): ModerationResult {
        $result = new ModerationResult;
        $result->moderation_run_id = $run->id;
        $result->field_key = $fieldKey;
        $result->check_key = $checkKey;
        $result->state = $state;
        $result->reason = $reason;
        $result->error = $error;
        $result->findings = $findings;
        $result->details = $details;
        $result->save();

        return $result;
    }
}
