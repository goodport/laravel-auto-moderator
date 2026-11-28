<?php

namespace Goodport\AutoModerator\Moderation;

use Goodport\AutoModerator\Models\ModerationResult;
use Goodport\AutoModerator\Models\ModerationRun;
use Goodport\AutoModerator\Support\ModerationValue;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads stored moderation runs and builds public outcomes.
 *
 * @internal
 */
final class ModerationOutcomeReader
{
    public function latest(Model $model, ?string $scenario = null): ?ModerationOutcome
    {
        $outcomes = $this->latestFor([$model], $scenario);

        return $outcomes[$model->getKey()] ?? null;
    }

    /**
     * @param  array<int, Model>  $models
     * @return array<int|string, ModerationOutcome>
     */
    public function latestFor(array $models, ?string $scenario = null): array
    {
        if ($models === []) {
            return [];
        }

        $morphType = $models[0]->getMorphClass();
        $ids = array_map(fn (Model $model): string => (string) $model->getKey(), $models);

        $runs = ModerationRun::query()
            ->whereIn('id', function ($query) use ($morphType, $ids, $scenario): void {
                $query->selectRaw('max(id)')
                    ->from('auto_moderator_runs')
                    ->where('moderatable_type', $morphType)
                    ->whereIn('moderatable_id', $ids)
                    ->when($scenario !== null, fn ($query) => $query->where('scenario', $scenario))
                    ->groupBy('moderatable_id');
            })
            ->get();

        $runs->load('results');

        $runsByModel = $runs->keyBy(
            fn (ModerationRun $run): string => (string) $run->moderatable_id,
        );

        $outcomes = [];

        foreach ($models as $model) {
            $run = $runsByModel->get((string) $model->getKey());

            if ($run !== null) {
                $outcomes[$model->getKey()] = $this->fromRun($run);
            }
        }

        return $outcomes;
    }

    public function fromRun(ModerationRun $run): ModerationOutcome
    {
        $status = $run->status->value;
        $labels = $run->field_labels ?? [];
        $rowsByField = [];

        foreach ($run->results as $result) {
            $rowsByField[$result->field_key][] = $result;
        }

        $fieldKeys = $this->fieldKeys($status, $labels, $rowsByField);
        $fields = [];

        foreach ($fieldKeys as $fieldKey) {
            $label = $labels[$fieldKey] ?? $fieldKey;
            $fields[$fieldKey] = $this->fieldOutcome(
                key: (string) $fieldKey,
                label: (string) $label,
                rows: $rowsByField[$fieldKey] ?? [],
            );
        }

        return new ModerationOutcome($run->scenario, $status, $fields);
    }

    /**
     * @param  array<string, string>  $labels
     * @param  array<string, array<int, ModerationResult>>  $rowsByField
     * @return array<int, string>
     */
    private function fieldKeys(string $status, array $labels, array $rowsByField): array
    {
        $synthesize = $labels !== [] && in_array($status, [
            ModerationOutcome::STATUS_APPROVED,
            ModerationOutcome::STATUS_REJECTED,
            ModerationOutcome::STATUS_NEEDS_REVIEW,
            ModerationOutcome::STATUS_SKIPPED,
        ], true);

        if (! $synthesize) {
            return array_keys($rowsByField);
        }

        $fieldKeys = array_keys($labels);

        foreach (array_keys($rowsByField) as $fieldKey) {
            if (! array_key_exists($fieldKey, $labels)) {
                $fieldKeys[] = $fieldKey;
            }
        }

        return $fieldKeys;
    }

    /**
     * @param  array<int, ModerationResult>  $rows
     */
    private function fieldOutcome(string $key, string $label, array $rows): ModerationFieldOutcome
    {
        $states = [];
        $reason = null;
        $error = null;
        $details = [];
        $findings = [];
        $reasonsByCheck = [];

        foreach ($rows as $row) {
            $state = $row->state;
            $states[] = $state;

            if (
                $reason === null
                && in_array($state, [ModerationState::Rejected, ModerationState::Undecided], true)
            ) {
                $reason = ModerationValue::firstNonEmpty($row->reason);
            }

            if ($error === null) {
                $error = ModerationValue::firstNonEmpty($row->error);
            }

            if (is_array($row->details)) {
                $details = array_replace($details, $row->details);
            }

            foreach ($row->findings ?? [] as $finding) {
                if (is_array($finding)) {
                    $finding['check_key'] = $row->check_key;
                    $findings[] = $finding;
                }
            }

            if (in_array($state, [ModerationState::Rejected, ModerationState::Undecided], true)) {
                $checkReason = ModerationValue::firstNonEmpty($row->reason, $row->error);

                if ($checkReason !== null) {
                    $reasonsByCheck[$row->check_key] = $checkReason;
                }
            }
        }

        return new ModerationFieldOutcome(
            key: $key,
            label: $label,
            state: ModerationState::aggregate($states)->value,
            reason: $reason,
            error: $error,
            details: $details,
            findings: $findings,
            reasonsByCheck: $reasonsByCheck,
        );
    }
}
