<?php

namespace Goodport\AutoModerator\Tests\Unit\Moderation;

use Goodport\AutoModerator\Moderation\ModerationFieldOutcome;
use Goodport\AutoModerator\Moderation\ModerationOutcome;
use PHPUnit\Framework\TestCase;

final class ModerationOutcomeTest extends TestCase
{
    /**
     * The outcome exposes status checks and field lookup.
     */
    public function test_outcome_exposes_status_and_field_checks(): void
    {
        $outcome = $this->outcome(ModerationOutcome::STATUS_REJECTED);

        $this->assertTrue($outcome->isRejected());
        $this->assertFalse($outcome->isApproved());
        $this->assertFalse($outcome->needsReview());
        $this->assertSame('body', $outcome->field('body')->key);
        $this->assertNull($outcome->field('missing'));
        $this->assertSame(['body', 'comment', 'title', 'subtitle'], array_keys($outcome->fields()));
    }

    /**
     * failed() covers rejected, undecided, and technical errors.
     */
    public function test_failed_covers_rejected_undecided_and_technical_errors(): void
    {
        $outcome = $this->outcome(ModerationOutcome::STATUS_REJECTED);

        $this->assertTrue($outcome->failed('body'));
        $this->assertTrue($outcome->failed('comment'));
        $this->assertFalse($outcome->failed('title'));
        $this->assertFalse($outcome->failed('subtitle'));
        $this->assertFalse($outcome->failed('missing'));
        $this->assertTrue($outcome->failedAny(['title', 'comment']));
        $this->assertFalse($outcome->failedAny(['title', 'subtitle']));
    }

    /**
     * reasonsForFields returns labeled strings in the requested order.
     */
    public function test_reasons_for_fields_keeps_field_keys_and_labels(): void
    {
        $outcome = $this->outcome(ModerationOutcome::STATUS_REJECTED);

        $this->assertSame([
            'body' => 'Body: Bad body',
            'comment' => 'Comment: Network error',
        ], $outcome->reasonsForFields(['body', 'title', 'comment', 'subtitle', 'missing']));
    }

    /**
     * The array representation contains only public field data.
     */
    public function test_to_array_contains_only_public_field_data(): void
    {
        $array = $this->outcome(ModerationOutcome::STATUS_REJECTED)->toArray();

        $this->assertSame('post', $array['scenario']);
        $this->assertSame('rejected', $array['status']);
        $this->assertSame([
            'key',
            'label',
            'state',
            'reason',
            'error',
            'details',
            'findings',
            'reasons_by_check',
        ], array_keys($array['fields']['body']));
        $this->assertSame('Body', $array['fields']['body']['label']);
        $this->assertSame(['age_rating' => 18], $array['fields']['body']['details']);
        $this->assertSame('reject', $array['fields']['body']['findings'][0]['check_key']);
        $this->assertSame(['reject' => 'Bad body'], $array['fields']['body']['reasons_by_check']);
        $this->assertSame('skipped', $array['fields']['subtitle']['state']);
        $this->assertArrayNotHasKey('id', $array['fields']['body']);
        $this->assertArrayNotHasKey('moderation_run_id', $array['fields']['body']);
    }

    /**
     * Public status and state lists are stable.
     */
    public function test_public_status_and_state_lists(): void
    {
        $this->assertSame([
            'running',
            'approved',
            'rejected',
            'needs_review',
            'skipped',
            'failed',
        ], ModerationOutcome::statuses());

        $this->assertSame([
            'passed',
            'rejected',
            'undecided',
            'skipped',
        ], ModerationFieldOutcome::states());
    }

    /**
     * A field outcome exposes convenience checks.
     */
    public function test_field_outcome_exposes_state_checks(): void
    {
        $field = $this->field('body', 'Body', ModerationFieldOutcome::STATE_UNDECIDED, null, 'Failure.');

        $this->assertTrue($field->isUndecided());
        $this->assertTrue($field->needsReview());
        $this->assertTrue($field->hasTechnicalError());
        $this->assertFalse($field->isPassed());
        $this->assertSame('Failure.', $field->error);
    }

    private function outcome(string $status): ModerationOutcome
    {
        return new ModerationOutcome('post', $status, [
            'body' => $this->field(
                'body',
                'Body',
                ModerationFieldOutcome::STATE_REJECTED,
                'Bad body',
                null,
                ['age_rating' => 18],
                [['check_key' => 'reject', 'code' => 'bad', 'blocking' => true]],
                ['reject' => 'Bad body'],
            ),
            'comment' => $this->field(
                'comment',
                'Comment',
                ModerationFieldOutcome::STATE_UNDECIDED,
                null,
                'Network error',
                [],
                [],
                ['ai' => 'Network error'],
            ),
            'title' => $this->field('title', 'Title', ModerationFieldOutcome::STATE_PASSED, null, null),
            'subtitle' => $this->field('subtitle', 'Subtitle', ModerationFieldOutcome::STATE_SKIPPED, null, null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<int, array<string, mixed>>  $findings
     * @param  array<string, string>  $reasonsByCheck
     */
    private function field(
        string $key,
        string $label,
        string $state,
        ?string $reason,
        ?string $error,
        array $details = [],
        array $findings = [],
        array $reasonsByCheck = [],
    ): ModerationFieldOutcome {
        return new ModerationFieldOutcome(
            key: $key,
            label: $label,
            state: $state,
            reason: $reason,
            error: $error,
            details: $details,
            findings: $findings,
            reasonsByCheck: $reasonsByCheck,
        );
    }
}
