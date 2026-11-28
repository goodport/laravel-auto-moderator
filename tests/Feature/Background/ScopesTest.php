<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Moderation\ModerationRunStatus;
use Goodport\AutoModerator\Moderation\ModerationState;
use Goodport\AutoModerator\Tests\Fixtures\ModeratableSubject;

final class ScopesTest extends BackgroundTestCase
{
    /**
     * The latest status scope uses only the newest run.
     */
    public function test_latest_status_scope_filters_by_latest_run(): void
    {
        $approvedLast = $this->createSubject(fields: []);
        $rejectedOnly = $this->createSubject(fields: []);
        $this->seedRun($approvedLast, ModerationRunStatus::Rejected);
        $this->seedRun($approvedLast, ModerationRunStatus::Approved);
        $this->seedRun($rejectedOnly, ModerationRunStatus::Rejected);

        $this->assertSame([$approvedLast->id], $this->subjectIdsWhereLatestStatus('approved'));
        $this->assertSame([$rejectedOnly->id], $this->subjectIdsWhereLatestStatus('rejected'));
        $this->assertSame(
            [$approvedLast->id, $rejectedOnly->id],
            $this->subjectIdsWhereLatestStatus(['approved', 'rejected']),
        );
    }

    /**
     * The any-run status scope matches historical runs.
     */
    public function test_status_scope_matches_any_run(): void
    {
        $subject = $this->createSubject(fields: []);
        $this->seedRun($subject, ModerationRunStatus::Rejected);
        $this->seedRun($subject, ModerationRunStatus::Approved);

        $this->assertSame(
            [$subject->id],
            ModeratableSubject::query()
                ->whereHasModerationStatus('rejected')
                ->pluck('id')
                ->all(),
        );
        $this->assertSame(
            [$subject->id],
            ModeratableSubject::query()
                ->whereHasModerationStatus('approved')
                ->pluck('id')
                ->all(),
        );
    }

    /**
     * The latest status scope applies the scenario filter before picking a run.
     */
    public function test_latest_status_scope_respects_scenario(): void
    {
        $subject = $this->createSubject(fields: []);
        $this->seedRun($subject, ModerationRunStatus::Rejected, 'one');
        $this->seedRun($subject, ModerationRunStatus::Approved, 'two');

        $this->assertSame([$subject->id], $this->subjectIdsWhereLatestStatus('rejected', 'one'));
        $this->assertSame([], $this->subjectIdsWhereLatestStatus('approved', 'one'));
        $this->assertSame([$subject->id], $this->subjectIdsWhereLatestStatus('approved', 'two'));
        $this->assertSame([], $this->subjectIdsWhereLatestStatus('rejected', 'two'));
    }

    /**
     * Run and result scopes filter by field, run history, and latest run.
     */
    public function test_run_and_result_scopes(): void
    {
        $first = $this->createSubject(fields: []);
        $second = $this->createSubject(fields: []);

        $older = $this->seedRun($first, ModerationRunStatus::Approved);
        $this->seedResult($older, 'body');
        $latest = $this->seedRun($first, ModerationRunStatus::Approved);
        $this->seedResult($latest, 'checklist');

        $secondLatest = $this->seedRun($second, ModerationRunStatus::Approved);
        $this->seedResult($secondLatest, 'body');

        $this->assertSame(
            [$first->id],
            $this->subjectIdsWhereLatestResult('checklist'),
        );
        $this->assertSame(
            [$first->id, $second->id],
            $this->subjectIdsWhereAnyResult('body'),
        );
        $this->assertSame(
            [$second->id],
            $this->subjectIdsWhereLatestResult('body'),
        );
        $this->assertSame(
            [$first->id],
            $this->subjectIdsWhereLatestResult('checklist', null, ModerationState::Passed->value),
        );
        $this->assertSame(
            [],
            $this->subjectIdsWhereLatestResult('checklist', null, ModerationState::Rejected->value),
        );
        $this->assertSame(
            [$first->id, $second->id],
            ModeratableSubject::query()->whereHasModerationRun()->orderBy('id')->pluck('id')->all(),
        );
        $this->assertSame(
            [],
            ModeratableSubject::query()->whereHasModerationRun('missing')->pluck('id')->all(),
        );
    }

    /**
     * Scopes reject unknown statuses, states, scenarios, and empty field keys.
     */
    public function test_scopes_reject_invalid_values(): void
    {
        $this->assertInvalidArgument(fn () => ModeratableSubject::query()->whereLatestModerationStatus('unknown'));
        $this->assertInvalidArgument(fn () => ModeratableSubject::query()->whereLatestModerationStatus([]));
        $this->assertInvalidArgument(fn () => ModeratableSubject::query()->whereHasModerationRun(''));
        $this->assertInvalidArgument(fn () => ModeratableSubject::query()->whereHasModerationResult(''));
        $this->assertInvalidArgument(
            fn () => ModeratableSubject::query()->whereHasModerationResult('body', null, 'unknown'),
        );
    }

    /**
     * Result and run scopes apply the scenario filter.
     */
    public function test_result_and_run_scopes_respect_scenario(): void
    {
        $subject = $this->createSubject(fields: []);
        $first = $this->seedRun($subject, ModerationRunStatus::Approved, 'one');
        $this->seedResult($first, 'body');
        $second = $this->seedRun($subject, ModerationRunStatus::Approved, 'two');
        $this->seedResult($second, 'checklist');

        $this->assertSame(
            [$subject->id],
            ModeratableSubject::query()
                ->whereHasModerationResult('body', 'one')
                ->pluck('id')
                ->all(),
        );
        $this->assertSame(
            [],
            ModeratableSubject::query()
                ->whereHasModerationResult('body', 'two')
                ->pluck('id')
                ->all(),
        );
        $this->assertSame(
            [$subject->id],
            ModeratableSubject::query()
                ->whereLatestModerationResult('checklist', 'two')
                ->pluck('id')
                ->all(),
        );
        $this->assertSame(
            [],
            ModeratableSubject::query()
                ->whereLatestModerationResult('checklist', 'one')
                ->pluck('id')
                ->all(),
        );
        $this->assertSame(
            [$subject->id],
            ModeratableSubject::query()
                ->whereHasModerationRun('one')
                ->pluck('id')
                ->all(),
        );
    }

    /**
     * The trait relations expose the run history and the latest run.
     */
    public function test_relations_expose_latest_run(): void
    {
        $subject = $this->createSubject(fields: []);
        $this->seedRun($subject, ModerationRunStatus::Rejected);
        $latest = $this->seedRun($subject, ModerationRunStatus::Approved);

        $this->assertSame(2, $subject->moderationRuns()->count());
        $this->assertSame($latest->id, $subject->latestModerationRun->id);
    }

    /**
     * @param  string|array<int, string>  $status
     * @return array<int, int>
     */
    private function subjectIdsWhereLatestStatus(string|array $status, ?string $scenario = null): array
    {
        return ModeratableSubject::query()
            ->whereLatestModerationStatus($status, $scenario)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function subjectIdsWhereLatestResult(
        string $fieldKey,
        ?string $scenario = null,
        ?string $state = null,
    ): array {
        return ModeratableSubject::query()
            ->whereLatestModerationResult($fieldKey, $scenario, $state)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function subjectIdsWhereAnyResult(string $fieldKey): array
    {
        return ModeratableSubject::query()
            ->whereHasModerationResult($fieldKey)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
