<?php

namespace Goodport\AutoModerator\Tests\Feature\ModerationCase;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Moderation\ModerationState;
use Goodport\AutoModerator\Tests\Fixtures\PassingCheck;
use Goodport\AutoModerator\Tests\Fixtures\RecordingCheck;
use Goodport\AutoModerator\Tests\Fixtures\RejectingCheck;
use Goodport\AutoModerator\Tests\Fixtures\TestActor;
use Goodport\AutoModerator\Tests\Fixtures\TestSubject;
use Goodport\AutoModerator\Tests\Fixtures\ThrowingCheck;
use Goodport\AutoModerator\Tests\TestCase;

final class RunTest extends TestCase
{
    /**
     * An empty field is skipped without resolving or running its profile.
     */
    public function test_empty_field_does_not_run_the_profile(): void
    {
        $this->profileWith('record', RecordingCheck::class);

        $result = AutoModerator::moderationCase()
            ->field('body', 'Body', 'record', ['text' => '   '])
            ->run();

        $this->assertSame([], RecordingCheck::$contexts);
        $this->assertTrue($result->result('body')->skipped);
        $this->assertNull($result->result('body')->report);
        $this->assertSame(ModerationState::Skipped, $result->state());
    }

    /**
     * An empty field is skipped before an unknown profile is resolved.
     */
    public function test_empty_field_does_not_resolve_an_unknown_profile(): void
    {
        $result = AutoModerator::moderationCase()
            ->field('body', 'Body', 'missing', ['text' => ''])
            ->run();

        $this->assertTrue($result->allSkipped());
        $this->assertNull($result->result('body')->report);
    }

    /**
     * Fields run in the exact order they were added.
     */
    public function test_fields_run_in_registration_order(): void
    {
        $this->profileWith('first', RecordingCheck::class);
        $this->profileWith('second', RecordingCheck::class);

        $result = AutoModerator::moderationCase()
            ->field('second', 'Second', 'second', ['text' => 'Second'])
            ->field('first', 'First', 'first', ['text' => 'First'])
            ->run();

        $this->assertSame(['second', 'first'], array_keys($result->results()));
        $this->assertSame(
            ['second', 'first'],
            array_map(fn (ModerationContext $context): string => $context->check, RecordingCheck::$contexts),
        );
    }

    /**
     * Every field keeps its own report, so all reasons are collected.
     */
    public function test_every_field_keeps_its_report(): void
    {
        $this->profileWith('reject', RejectingCheck::class);

        $result = AutoModerator::moderationCase()
            ->field('body', 'Body', 'reject', ['text' => 'Body'])
            ->field('comment', 'Comment', 'reject', ['text' => 'Comment'])
            ->run();

        $this->assertTrue($result->rejected());
        $this->assertCount(2, $result->results());
        $this->assertTrue($result->result('body')->report->rejected());
        $this->assertTrue($result->result('comment')->report->rejected());
        $this->assertSame('Rejected by fixture.', $result->firstReason());
    }

    /**
     * A rejection in a later field dominates an undecided earlier field.
     */
    public function test_rejection_dominates_undecided_across_fields(): void
    {
        $this->profileWith('throw', ThrowingCheck::class);
        $this->profileWith('reject', RejectingCheck::class);

        $result = AutoModerator::moderationCase()
            ->field('body', 'Body', 'throw', ['text' => 'Body'])
            ->field('comment', 'Comment', 'reject', ['text' => 'Comment'])
            ->run();

        $this->assertTrue($result->rejected());
        $this->assertSame('Rejected by fixture.', $result->firstReason());
    }

    /**
     * An undecided field is not turned into a pass.
     */
    public function test_undecided_is_not_passed(): void
    {
        $this->profileWith('pass', PassingCheck::class);
        $this->profileWith('throw', ThrowingCheck::class);

        $result = AutoModerator::moderationCase()
            ->field('body', 'Body', 'pass', ['text' => 'Body'])
            ->field('comment', 'Comment', 'throw', ['text' => 'Comment'])
            ->run();

        $this->assertTrue($result->undecided());
        $this->assertNull($result->passed());
        $this->assertSame('Fixture failure.', $result->firstReason());
    }

    /**
     * A case where every field is empty is skipped.
     */
    public function test_all_empty_fields_are_skipped(): void
    {
        $this->profileWith('pass', PassingCheck::class);

        $result = AutoModerator::moderationCase()
            ->field('body', 'Body', 'pass', ['text' => ''])
            ->field('comment', 'Comment', 'pass', ['text' => '  ', 'tags' => []])
            ->run();

        $this->assertTrue($result->allSkipped());
        $this->assertNull($result->passed());
        $this->assertNull($result->firstReason());
        $this->assertCount(2, $result->results());
        $this->assertNull($result->result('body')->report);
        $this->assertNull($result->result('comment')->report);
    }

    /**
     * The actor, subject, language, and audit context are shared by all fields.
     */
    public function test_case_context_is_shared_by_all_fields(): void
    {
        $this->profileWith('record', RecordingCheck::class);

        $actor = new TestActor;
        $actor->id = 2;
        $subject = new TestSubject;
        $subject->id = 3;

        AutoModerator::moderationCase()
            ->actor($actor)
            ->subject($subject)
            ->language('en')
            ->field('body', 'Body', 'record', ['text' => 'Body'])
            ->field('comment', 'Comment', 'record', ['text' => 'Comment'])
            ->run();

        $this->assertCount(2, RecordingCheck::$contexts);

        foreach (RecordingCheck::$contexts as $context) {
            $this->assertSame('en', $context->language);
            $this->assertSame(RecordingCheck::$contexts[0]->audit, $context->audit);
        }

        $audit = RecordingCheck::$contexts[0]->audit;

        $this->assertNotNull($audit);
        $this->assertSame('2', $audit->actorId);
        $this->assertSame(TestSubject::class, $audit->subjectType);
        $this->assertSame('3', $audit->subjectId);
    }

    /**
     * An explicit audit context is shared by all fields.
     */
    public function test_explicit_audit_is_shared_by_all_fields(): void
    {
        $this->profileWith('record', RecordingCheck::class);
        $audit = new AuditContext(
            actorType: 'explicit',
            actorId: '9',
            requestUrl: 'https://explicit.test',
        );

        AutoModerator::moderationCase()
            ->audit($audit)
            ->field('body', 'Body', 'record', ['text' => 'Body'])
            ->field('comment', 'Comment', 'record', ['text' => 'Comment'])
            ->run();

        $this->assertCount(2, RecordingCheck::$contexts);
        $this->assertSame($audit, RecordingCheck::$contexts[0]->audit);
        $this->assertSame($audit, RecordingCheck::$contexts[1]->audit);
    }

    /**
     * An unknown profile in a non-empty field throws.
     */
    public function test_unknown_profile_in_a_non_empty_field_throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::moderationCase()
            ->field('body', 'Body', 'missing', ['text' => 'Body'])
            ->run();
    }

    /**
     * An invalid later profile fails before any field runs.
     */
    public function test_invalid_late_profile_fails_before_any_field_runs(): void
    {
        $this->profileWith('record', RecordingCheck::class);

        try {
            AutoModerator::moderationCase()
                ->field('body', 'Body', 'record', ['text' => 'Body'])
                ->field('comment', 'Comment', 'missing', ['text' => 'Comment'])
                ->run();
        } catch (InvalidConfigurationException) {
            $this->assertSame([], RecordingCheck::$contexts);

            return;
        }

        $this->fail('An unknown profile must throw before any field runs.');
    }

    /**
     * The array representation keeps field keys, labels, and reports.
     */
    public function test_to_array_contains_fields_and_reports(): void
    {
        $this->profileWith('pass', PassingCheck::class);

        $array = AutoModerator::moderationCase()
            ->field('body', 'Body', 'pass', ['text' => 'Body'])
            ->field('comment', 'Comment', 'pass', ['text' => '   '])
            ->run()
            ->toArray();

        $this->assertSame('passed', $array['state']);
        $this->assertSame(['body', 'comment'], array_keys($array['fields']));
        $this->assertSame('Body', $array['fields']['body']['label']);
        $this->assertFalse($array['fields']['body']['skipped']);
        $this->assertSame('passed', $array['fields']['body']['state']);
        $this->assertSame('passed', $array['fields']['body']['report']['state']);
        $this->assertSame('Comment', $array['fields']['comment']['label']);
        $this->assertTrue($array['fields']['comment']['skipped']);
        $this->assertSame('skipped', $array['fields']['comment']['state']);
        $this->assertNull($array['fields']['comment']['report']);
    }

    /**
     * A duplicated field key is rejected when the field is added.
     */
    public function test_duplicate_field_key_is_rejected_immediately(): void
    {
        $this->expectException(InvalidInputException::class);

        AutoModerator::moderationCase()
            ->field('body', 'Body', 'pass', [])
            ->field('body', 'Other body', 'pass', []);
    }

    private function profileWith(string $name, string $check): void
    {
        $this->setCheck($name, $check);
        $this->setProfile($name, ['checks' => [['check' => $name]]]);
    }
}
