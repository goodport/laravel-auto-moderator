<?php

namespace Goodport\AutoModerator\Concerns;

use Closure;
use Goodport\AutoModerator\Models\ModerationRun;
use Goodport\AutoModerator\Support\ModerationQueryGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Moderation relations and query scopes for a moderatable model.
 */
trait HasModerationRuns
{
    public function moderationRuns(): MorphMany
    {
        return $this->morphMany(ModerationRun::class, 'moderatable');
    }

    public function latestModerationRun(): MorphOne
    {
        return $this->morphOne(ModerationRun::class, 'moderatable')->latestOfMany();
    }

    public function scopeWhereLatestModerationStatus(
        Builder $query,
        string|array $status,
        ?string $scenario = null,
    ): void {
        $statuses = ModerationQueryGuard::statuses($status);
        $scenario = ModerationQueryGuard::scenario($scenario);

        $this->applyLatestRun($query, $scenario, function (Builder $runs) use ($statuses): void {
            $runs->whereIn('status', $statuses);
        });
    }

    public function scopeWhereHasModerationStatus(
        Builder $query,
        string|array $status,
        ?string $scenario = null,
    ): void {
        $statuses = ModerationQueryGuard::statuses($status);
        $scenario = ModerationQueryGuard::scenario($scenario);

        $query->whereHas('moderationRuns', function (Builder $runs) use ($statuses, $scenario): void {
            $runs->whereIn('status', $statuses);

            if ($scenario !== null) {
                $runs->where('scenario', $scenario);
            }
        });
    }

    public function scopeWhereHasModerationRun(Builder $query, ?string $scenario = null): void
    {
        $scenario = ModerationQueryGuard::scenario($scenario);

        $query->whereHas('moderationRuns', function (Builder $runs) use ($scenario): void {
            if ($scenario !== null) {
                $runs->where('scenario', $scenario);
            }
        });
    }

    public function scopeWhereHasModerationResult(
        Builder $query,
        string $fieldKey,
        ?string $scenario = null,
        ?string $state = null,
    ): void {
        $fieldKey = ModerationQueryGuard::fieldKey($fieldKey);
        $scenario = ModerationQueryGuard::scenario($scenario);
        $state = ModerationQueryGuard::state($state);

        $query->whereHas('moderationRuns', function (Builder $runs) use ($fieldKey, $scenario, $state): void {
            if ($scenario !== null) {
                $runs->where('scenario', $scenario);
            }

            $runs->whereHas(
                'results',
                fn (Builder $results) => $this->constrainResult($results, $fieldKey, $state),
            );
        });
    }

    public function scopeWhereLatestModerationResult(
        Builder $query,
        string $fieldKey,
        ?string $scenario = null,
        ?string $state = null,
    ): void {
        $fieldKey = ModerationQueryGuard::fieldKey($fieldKey);
        $scenario = ModerationQueryGuard::scenario($scenario);
        $state = ModerationQueryGuard::state($state);

        $this->applyLatestRun($query, $scenario, function (Builder $runs) use ($fieldKey, $state): void {
            $runs->whereHas(
                'results',
                fn (Builder $results) => $this->constrainResult($results, $fieldKey, $state),
            );
        });
    }

    /**
     * @param  Closure(Builder): void  $constraint
     */
    private function applyLatestRun(Builder $query, ?string $scenario, Closure $constraint): void
    {
        $query->whereHas('moderationRuns', function (Builder $runs) use ($scenario, $constraint): void {
            $constraint($runs);

            if ($scenario !== null) {
                $runs->where('scenario', $scenario);
            }

            $runs->whereNotExists(function ($newer) use ($scenario): void {
                $newer->selectRaw('1')
                    ->from('auto_moderator_runs as newer')
                    ->whereColumn('newer.moderatable_type', 'auto_moderator_runs.moderatable_type')
                    ->whereColumn('newer.moderatable_id', 'auto_moderator_runs.moderatable_id')
                    ->whereColumn('newer.id', '>', 'auto_moderator_runs.id');

                if ($scenario !== null) {
                    $newer->where('newer.scenario', $scenario);
                }
            });
        });
    }

    private function constrainResult(Builder $results, string $fieldKey, ?string $state): void
    {
        $results->where('field_key', $fieldKey);

        if ($state !== null) {
            $results->where('state', $state);
        }
    }
}
