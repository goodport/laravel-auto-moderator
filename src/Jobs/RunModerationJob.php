<?php

namespace Goodport\AutoModerator\Jobs;

use Goodport\AutoModerator\AutoModeratorManager;
use Goodport\AutoModerator\Contracts\Moderatable;
use Goodport\AutoModerator\Events\ModerationCompleted;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Models\ModerationResult;
use Goodport\AutoModerator\Models\ModerationRun;
use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\ModerationCaseResult;
use Goodport\AutoModerator\Moderation\ModerationRunStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs and stores one background moderation attempt.
 */
final class RunModerationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly string $morphType,
        public readonly string|int $morphId,
        public readonly string $scenario,
    ) {}

    public function uniqueId(): string
    {
        return implode('|', [$this->morphType, (string) $this->morphId, $this->scenario]);
    }

    public function uniqueFor(): int
    {
        $seconds = config('auto-moderator.background.unique_for_seconds', 3600);

        if (! is_int($seconds) || $seconds < 1) {
            throw InvalidConfigurationException::invalidSetting(
                'background.unique_for_seconds',
                'must be a positive integer.',
            );
        }

        return $seconds;
    }

    public function handle(AutoModeratorManager $autoModerator): void
    {
        $model = $this->findModeratable();

        if ($model === null) {
            return;
        }

        $run = $this->startRun($model);

        try {
            $completed = $this->moderate($run, $model, $autoModerator);
        } catch (Throwable $exception) {
            $this->fail($run, $exception);

            throw $exception;
        }

        event(new ModerationCompleted($completed));
    }

    private function findModeratable(): ?Model
    {
        $class = Relation::getMorphedModel($this->morphType) ?? $this->morphType;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw InvalidConfigurationException::unknownModeratableType($this->morphType);
        }

        return $class::query()->find($this->morphId);
    }

    private function startRun(Model $model): ModerationRun
    {
        $run = new ModerationRun;
        $run->moderatable_type = $model->getMorphClass();
        $run->moderatable_id = (string) $model->getKey();
        $run->scenario = $this->scenario;
        $run->status = ModerationRunStatus::Running;
        $run->started_at = now();
        $run->save();

        return $run;
    }

    private function moderate(ModerationRun $run, Model $model, AutoModeratorManager $autoModerator): ModerationRun
    {
        if (! $model instanceof Moderatable) {
            throw InvalidConfigurationException::notModeratable($model::class);
        }

        $actualScenario = $model->moderationScenario();

        if ($actualScenario !== $this->scenario) {
            throw InvalidConfigurationException::scenarioMismatch($this->scenario, $actualScenario);
        }

        $language = $model->moderationLanguage();
        $audit = AuditContext::fromActorAndSubject($model->moderationActor(), $model);

        $run->context = [
            'language' => $language,
            'audit' => $audit->toArray(),
        ];
        $run->save();

        $caseResult = $this->runCase($autoModerator, $model, $language, $audit);

        DB::transaction(function () use ($run, $caseResult): void {
            $this->saveResults($run, $caseResult);

            $run->status = ModerationRunStatus::fromCaseResult($caseResult);
            $run->reason = $caseResult->firstReason();
            $run->finished_at = now();
            $run->save();
        });

        return $run->load('results');
    }

    private function runCase(
        AutoModeratorManager $autoModerator,
        Model&Moderatable $model,
        ?string $language,
        AuditContext $audit,
    ): ModerationCaseResult {
        $case = $autoModerator->moderationCase()->audit($audit);

        if ($language !== null) {
            $case->language($language);
        }

        foreach ($model->moderationFields() as $field) {
            $case->field($field->key, $field->label, $field->profile, $field->input);
        }

        return $case->run();
    }

    private function saveResults(ModerationRun $run, ModerationCaseResult $caseResult): void
    {
        foreach ($caseResult->results() as $fieldResult) {
            $report = $fieldResult->report;

            if ($report === null) {
                continue;
            }

            foreach ($report->results() as $checkKey => $checkResult) {
                $result = new ModerationResult;
                $result->moderation_run_id = $run->id;
                $result->field_key = $fieldResult->key;
                $result->check_key = $checkKey;
                $result->state = $checkResult->state;
                $result->reason = $checkResult->reason;
                $result->error = $checkResult->error;
                $result->findings = array_map(
                    fn (Finding $finding): array => $finding->toArray(),
                    $checkResult->findings,
                );
                $result->details = $checkResult->details;
                $result->save();
            }
        }
    }

    private function fail(ModerationRun $run, Throwable $exception): void
    {
        $run->status = ModerationRunStatus::Failed;
        $run->reason = $exception->getMessage();
        $run->finished_at = now();
        $run->save();
    }
}
