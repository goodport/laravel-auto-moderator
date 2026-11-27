<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Events\ModerationCompleted;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Jobs\RunModerationJob;
use Goodport\AutoModerator\Models\ModerationRun;
use Goodport\AutoModerator\Moderation\ModerationRunStatus;
use Goodport\AutoModerator\Moderation\ModerationState;
use Goodport\AutoModerator\Tests\Fixtures\DetailedCheck;
use Goodport\AutoModerator\Tests\Fixtures\ModeratableSubject;
use Goodport\AutoModerator\Tests\Fixtures\PassingCheck;
use Goodport\AutoModerator\Tests\Fixtures\RejectingCheck;
use Goodport\AutoModerator\Tests\Fixtures\TestActor;
use Goodport\AutoModerator\Tests\Fixtures\TestSubject;
use Goodport\AutoModerator\Tests\Fixtures\ThrowingCheck;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class RunModerationJobTest extends BackgroundTestCase
{
    /**
     * A run and its check results are stored with a serialized context.
     */
    public function test_creates_run_and_saves_mapped_results(): void
    {
        $this->registerProfile('detailed', DetailedCheck::class);
        $subject = $this->createSubject(
            fields: [[
                'key' => 'body',
                'label' => 'Body',
                'profile' => 'detailed',
                'input' => ['text' => 'Hello'],
            ]],
            language: 'ru',
            actorId: 5,
        );

        $this->handle($this->job($subject));

        $run = ModerationRun::query()->sole();

        $this->assertSame(ModerationRunStatus::Approved, $run->status);
        $this->assertSame('subject_scenario', $run->scenario);
        $this->assertSame(ModeratableSubject::class, $run->moderatable_type);
        $this->assertSame((string) $subject->id, $run->moderatable_id);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        $this->assertNull($run->reason);
        $this->assertSame('ru', $run->context['language']);
        $this->assertSame(TestActor::class, $run->context['audit']['actor_type']);
        $this->assertSame('5', $run->context['audit']['actor_id']);
        $this->assertSame(ModeratableSubject::class, $run->context['audit']['subject_type']);
        $this->assertSame((string) $subject->id, $run->context['audit']['subject_id']);
        $this->assertNull($run->context['audit']['request_url']);

        $result = $run->results()->sole();

        $this->assertSame('body', $result->field_key);
        $this->assertSame('detailed', $result->check_key);
        $this->assertSame(ModerationState::Passed, $result->state);
        $this->assertNull($result->reason);
        $this->assertNull($result->error);
        $this->assertSame([
            [
                'code' => 'fixture_note',
                'message' => 'A note.',
                'severity' => 'low',
                'blocking' => false,
                'details' => ['source' => 'fixture'],
            ],
        ], $result->findings);
        $this->assertSame(['checker' => 'fixture'], $result->details);

        $this->assertSame('Hello', $subject->fresh()->fields[0]['input']['text']);
    }

    /**
     * A blocking rejection makes the run rejected.
     */
    public function test_rejected_run_keeps_reason_and_findings(): void
    {
        $this->registerProfile('reject', RejectingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->field('body', 'Body', 'reject', 'Body'),
        ]);

        $this->handle($this->job($subject));

        $run = ModerationRun::query()->sole();
        $this->assertSame(ModerationRunStatus::Rejected, $run->status);
        $this->assertSame('Rejected by fixture.', $run->reason);

        $result = $run->results()->sole();
        $this->assertSame(ModerationState::Rejected, $result->state);
        $this->assertSame('Rejected by fixture.', $result->reason);
        $this->assertNull($result->error);
        $this->assertTrue($result->findings[0]['blocking']);
    }

    /**
     * An undecided check becomes needs_review and keeps the technical error.
     */
    public function test_undecided_check_becomes_needs_review(): void
    {
        $this->registerProfile('throw', ThrowingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->field('body', 'Body', 'throw', 'Body'),
        ]);

        $this->handle($this->job($subject));

        $run = ModerationRun::query()->sole();
        $this->assertSame(ModerationRunStatus::NeedsReview, $run->status);
        $this->assertSame('Fixture failure.', $run->reason);

        $result = $run->results()->sole();
        $this->assertSame(ModerationState::Undecided, $result->state);
        $this->assertNull($result->reason);
        $this->assertSame('Fixture failure.', $result->error);
    }

    /**
     * An empty field produces a skipped run without result rows.
     */
    public function test_empty_field_produces_skipped_run_without_results(): void
    {
        $subject = $this->createSubject(fields: [
            $this->field('body', 'Body', 'missing', '   '),
        ]);

        $this->handle($this->job($subject));

        $run = ModerationRun::query()->sole();
        $this->assertSame(ModerationRunStatus::Skipped, $run->status);
        $this->assertNull($run->reason);
        $this->assertNotNull($run->context);
        $this->assertNull($run->context['audit']['actor_type']);
        $this->assertNull($run->context['audit']['actor_id']);
        $this->assertSame(ModeratableSubject::class, $run->context['audit']['subject_type']);
        $this->assertSame((string) $subject->id, $run->context['audit']['subject_id']);
        $this->assertSame(0, $run->results()->count());
    }

    /**
     * A skipped check of a non-empty field is stored.
     */
    public function test_skipped_check_of_non_empty_field_is_saved(): void
    {
        $this->setCheck('pass', PassingCheck::class);
        $this->setProfile('disabled', [
            'checks' => [
                ['check' => 'pass', 'enabled' => false],
            ],
        ]);
        $subject = $this->createSubject(fields: [
            $this->field('body', 'Body', 'disabled', 'Body'),
        ]);

        $this->handle($this->job($subject));

        $run = ModerationRun::query()->sole();
        $this->assertSame(ModerationRunStatus::Skipped, $run->status);

        $result = $run->results()->sole();
        $this->assertSame('pass', $result->check_key);
        $this->assertSame(ModerationState::Skipped, $result->state);
    }

    /**
     * A rejection dominates an undecided field within the run status.
     */
    public function test_rejection_dominates_undecided(): void
    {
        $this->registerProfile('throw', ThrowingCheck::class);
        $this->registerProfile('reject', RejectingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->field('body', 'Body', 'throw', 'Body'),
            $this->field('comment', 'Comment', 'reject', 'Comment'),
        ]);

        $this->handle($this->job($subject));

        $run = ModerationRun::query()->sole();
        $this->assertSame(ModerationRunStatus::Rejected, $run->status);
        $this->assertSame('Rejected by fixture.', $run->reason);
        $this->assertSame(2, $run->results()->count());
    }

    /**
     * A scenario mismatch marks the run failed and does not publish the event.
     */
    public function test_scenario_mismatch_fails_run_without_event(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(
            fields: [$this->field('body', 'Body', 'pass', 'Body')],
            scenario: 'actual_scenario',
        );
        Event::fake([ModerationCompleted::class]);

        $this->assertJobFails($this->job($subject, 'expected_scenario'));

        $run = ModerationRun::query()->sole();
        $this->assertSame(ModerationRunStatus::Failed, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertNull($run->context);
        $this->assertSame(0, $run->results()->count());
        $this->assertStringContainsString('expected_scenario', $run->reason);
        $this->assertStringContainsString('actual_scenario', $run->reason);
        Event::assertNotDispatched(ModerationCompleted::class);
    }

    /**
     * An unknown profile marks the run failed after collecting its context.
     */
    public function test_unknown_profile_fails_run(): void
    {
        $subject = $this->createSubject(fields: [
            $this->field('body', 'Body', 'missing', 'Body'),
        ]);
        Event::fake([ModerationCompleted::class]);

        $this->assertJobFails($this->job($subject));

        $run = ModerationRun::query()->sole();
        $this->assertSame(ModerationRunStatus::Failed, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->context);
        $this->assertSame(0, $run->results()->count());
        $this->assertStringContainsString('missing', $run->reason);
        Event::assertNotDispatched(ModerationCompleted::class);
    }

    /**
     * A model without the contract creates a failed run.
     */
    public function test_model_without_contract_creates_failed_run(): void
    {
        $subject = new TestSubject;
        $subject->save();
        Event::fake([ModerationCompleted::class]);

        $this->assertJobFails(new RunModerationJob(TestSubject::class, $subject->getKey(), 'subject'));

        $run = ModerationRun::query()->sole();
        $this->assertSame(ModerationRunStatus::Failed, $run->status);
        $this->assertSame(TestSubject::class, $run->moderatable_type);
        $this->assertNull($run->context);
        $this->assertSame(0, $run->results()->count());
        Event::assertNotDispatched(ModerationCompleted::class);
    }

    /**
     * A missing model is skipped silently.
     */
    public function test_missing_model_does_nothing(): void
    {
        $this->handle(new RunModerationJob(ModeratableSubject::class, 999999, 'subject_scenario'));

        $this->assertSame(0, ModerationRun::query()->count());
    }

    /**
     * A morph map alias resolves the moderated model.
     */
    public function test_finds_model_by_morph_map(): void
    {
        Relation::morphMap(['moderatable_subject' => ModeratableSubject::class]);
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->field('body', 'Body', 'pass', 'Body'),
        ]);

        $this->handle(new RunModerationJob('moderatable_subject', $subject->getKey(), 'subject_scenario'));

        $run = ModerationRun::query()->sole();
        $this->assertSame('moderatable_subject', $run->moderatable_type);
        $this->assertSame(1, $run->results()->count());
    }

    /**
     * A completed run publishes the event with loaded results.
     */
    public function test_completed_run_publishes_event_with_results(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->field('body', 'Body', 'pass', 'Body'),
        ]);
        Event::fake([ModerationCompleted::class]);

        $this->handle($this->job($subject));

        Event::assertDispatched(ModerationCompleted::class, function (ModerationCompleted $event): bool {
            return $event->run->status === ModerationRunStatus::Approved
                && $event->run->relationLoaded('results')
                && $event->run->results->count() === 1;
        });
    }

    /**
     * The unique lock has a finite lifetime.
     */
    public function test_unique_lock_has_a_finite_lifetime(): void
    {
        $job = new RunModerationJob(ModeratableSubject::class, 1, 'subject_scenario');

        $this->assertGreaterThan(0, $job->uniqueFor());
    }

    /**
     * A run writes only package tables and leaves the moderated model untouched.
     */
    public function test_run_writes_only_package_tables(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->field('body', 'Body', 'pass', 'Body'),
        ]);

        $this->handle($this->job($subject));

        $this->assertSame(1, DB::table('moderatable_subjects')->count());
        $this->assertSame(1, DB::table('auto_moderator_runs')->count());
        $this->assertSame(1, DB::table('auto_moderator_results')->count());

        $fresh = $subject->fresh();
        $this->assertSame($subject->fields, $fresh->fields);
        $this->assertSame(
            $subject->updated_at->toDateTimeString(),
            $fresh->updated_at->toDateTimeString(),
        );
    }

    /**
     * @param  array{key: string, label: string, profile: string, input: array<string, mixed>}  $field
     * @return array{key: string, label: string, profile: string, input: array<string, mixed>}
     */
    private function field(string $key, string $label, string $profile, string $text): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'profile' => $profile,
            'input' => ['text' => $text],
        ];
    }

    private function job(ModeratableSubject $subject, ?string $scenario = null): RunModerationJob
    {
        return new RunModerationJob(
            morphType: $subject->getMorphClass(),
            morphId: $subject->getKey(),
            scenario: $scenario ?? $subject->moderationScenario(),
        );
    }

    private function assertJobFails(RunModerationJob $job): void
    {
        try {
            $this->handle($job);
        } catch (InvalidConfigurationException) {
            return;
        }

        $this->fail('The moderation job must throw a configuration exception.');
    }
}
