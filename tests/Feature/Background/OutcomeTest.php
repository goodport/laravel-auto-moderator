<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Moderation\ModerationFieldOutcome;
use Goodport\AutoModerator\Moderation\ModerationRunStatus;
use Goodport\AutoModerator\Moderation\ModerationState;
use Goodport\AutoModerator\Tests\Fixtures\AgeRatingCheck;
use Goodport\AutoModerator\Tests\Fixtures\DetailedCheck;
use Goodport\AutoModerator\Tests\Fixtures\EmptyErrorPassingCheck;
use Goodport\AutoModerator\Tests\Fixtures\EmptyReasonCheck;
use Goodport\AutoModerator\Tests\Fixtures\RejectingCheck;
use Goodport\AutoModerator\Tests\Fixtures\ThrowingCheck;
use Goodport\AutoModerator\Tests\Fixtures\WarningCheck;

final class OutcomeTest extends BackgroundTestCase
{
    /**
     * Field details are merged and findings keep their check key.
     */
    public function test_outcome_merges_details_and_findings(): void
    {
        $this->setCheck('detailed', DetailedCheck::class);
        $this->setCheck('warning', WarningCheck::class);
        $this->setCheck('age', AgeRatingCheck::class);
        $this->setProfile('combined', [
            'checks' => [
                ['check' => 'detailed'],
                ['check' => 'warning'],
                ['check' => 'age'],
            ],
        ]);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'combined', 'Body'),
        ]);

        $this->handle($this->moderationJob($subject));

        $outcome = $this->latestOutcome($subject);
        $field = $outcome->field('body');

        $this->assertTrue($outcome->isApproved());
        $this->assertSame(ModerationFieldOutcome::STATE_PASSED, $field->state);
        $this->assertSame(['checker' => 'fixture', 'age_rating' => 18], $field->details);
        $this->assertSame(['detailed', 'warning'], array_column($field->findings, 'check_key'));
    }

    /**
     * A skipped declared field is present in the outcome.
     */
    public function test_rejected_outcome_includes_skipped_declared_fields(): void
    {
        $this->registerProfile('reject', RejectingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'reject', 'Body'),
            $this->moderationField('comment', 'Comment', 'missing', '   '),
        ]);

        $this->handle($this->moderationJob($subject));

        $outcome = $this->latestOutcome($subject);
        $body = $outcome->field('body');
        $comment = $outcome->field('comment');

        $this->assertTrue($outcome->isRejected());
        $this->assertSame(['body', 'comment'], array_keys($outcome->fields()));
        $this->assertSame(ModerationFieldOutcome::STATE_REJECTED, $body->state);
        $this->assertSame(['reject' => 'Rejected by fixture.'], $body->reasonsByCheck);
        $this->assertSame('reject', $body->findings[0]['check_key']);
        $this->assertTrue($comment->isSkipped());
        $this->assertSame('Comment', $comment->label);
        $this->assertTrue($outcome->failed('body'));
        $this->assertFalse($outcome->failed('comment'));
        $this->assertSame([
            'body' => 'Body: Rejected by fixture.',
        ], $outcome->reasonsForFields(['body', 'comment']));
    }

    /**
     * An undecided check with an error makes the run needs_review.
     */
    public function test_undecided_error_makes_run_needs_review(): void
    {
        $this->registerProfile('throw', ThrowingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'throw', 'Body'),
        ]);

        $this->handle($this->moderationJob($subject));

        $outcome = $this->latestOutcome($subject);
        $field = $outcome->field('body');

        $this->assertTrue($outcome->needsReview());
        $this->assertTrue($field->isUndecided());
        $this->assertTrue($field->hasTechnicalError());
        $this->assertSame('Fixture failure.', $field->error);
        $this->assertSame(['throw' => 'Fixture failure.'], $field->reasonsByCheck);
        $this->assertSame([
            'body' => 'Body: Fixture failure.',
        ], $outcome->reasonsForFields(['body']));
    }

    /**
     * An empty reason does not hide the technical error or a later reason.
     */
    public function test_empty_reason_does_not_hide_error_or_later_reason(): void
    {
        $this->setCheck('empty_reason', EmptyReasonCheck::class);
        $this->setCheck('reject', RejectingCheck::class);
        $this->setProfile('mixed', [
            'checks' => [
                ['check' => 'empty_reason'],
                ['check' => 'reject'],
            ],
        ]);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'mixed', 'Body'),
        ]);

        $this->handle($this->moderationJob($subject));

        $outcome = $this->latestOutcome($subject);
        $field = $outcome->field('body');

        $this->assertTrue($outcome->isRejected());
        $this->assertSame(ModerationFieldOutcome::STATE_REJECTED, $field->state);
        $this->assertSame('Rejected by fixture.', $field->reason);
        $this->assertSame('Technical failure.', $field->error);
        $this->assertTrue($field->hasTechnicalError());
        $this->assertSame([
            'empty_reason' => 'Technical failure.',
            'reject' => 'Rejected by fixture.',
        ], $field->reasonsByCheck);
        $this->assertSame([
            'body' => 'Body: Rejected by fixture.',
        ], $outcome->reasonsForFields(['body']));
    }

    /**
     * An empty error string is not a technical error.
     */
    public function test_empty_error_is_not_a_technical_error(): void
    {
        $this->registerProfile('empty_error', EmptyErrorPassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'empty_error', 'Body'),
        ]);

        $this->handle($this->moderationJob($subject));

        $outcome = $this->latestOutcome($subject);
        $field = $outcome->field('body');

        $this->assertTrue($outcome->isApproved());
        $this->assertSame(ModerationFieldOutcome::STATE_PASSED, $field->state);
        $this->assertNull($field->error);
        $this->assertFalse($field->hasTechnicalError());
        $this->assertSame([], $outcome->reasonsForFields(['body']));
    }

    /**
     * A skipped run includes every declared field.
     */
    public function test_skipped_run_includes_all_declared_fields(): void
    {
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'missing', '   '),
            $this->moderationField('title', 'Title', 'missing', ''),
        ]);

        $this->handle($this->moderationJob($subject));

        $outcome = $this->latestOutcome($subject);

        $this->assertTrue($outcome->isSkipped());
        $this->assertSame(['body', 'title'], array_keys($outcome->fields()));
        $this->assertTrue($outcome->field('body')->isSkipped());
        $this->assertTrue($outcome->field('title')->isSkipped());
        $this->assertSame('Title', $outcome->field('title')->label);
    }

    /**
     * Running and failed runs do not synthesize declared fields.
     */
    public function test_running_and_failed_outcomes_use_only_real_result_rows(): void
    {
        $subject = $this->createSubject(fields: []);
        $labels = ['body' => 'Body', 'comment' => 'Comment'];

        $this->seedRun($subject, ModerationRunStatus::Running, fieldLabels: $labels);
        $running = $this->latestOutcome($subject);

        $this->assertTrue($running->isRunning());
        $this->assertSame([], $running->fields());

        $failed = $this->seedRun($subject, ModerationRunStatus::Failed, fieldLabels: $labels);
        $this->seedResult($failed, 'body', ModerationState::Passed);

        $outcome = $this->latestOutcome($subject);

        $this->assertTrue($outcome->isFailed());
        $this->assertSame(['body'], array_keys($outcome->fields()));
        $this->assertSame('Body', $outcome->field('body')->label);
    }

    /**
     * A terminal run without labels falls back to field keys.
     */
    public function test_old_terminal_run_without_labels_uses_field_keys(): void
    {
        $subject = $this->createSubject(fields: []);
        $run = $this->seedRun($subject, ModerationRunStatus::Approved);
        $this->seedResult($run, 'body', ModerationState::Passed);

        $outcome = $this->latestOutcome($subject);

        $this->assertSame(['body'], array_keys($outcome->fields()));
        $this->assertSame('body', $outcome->field('body')->label);
    }
}
