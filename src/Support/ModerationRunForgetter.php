<?php

namespace Goodport\AutoModerator\Support;

use Goodport\AutoModerator\Models\ModerationResult;
use Goodport\AutoModerator\Models\ModerationRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Deletes stored moderation runs and results for one model.
 *
 * @internal
 */
final class ModerationRunForgetter
{
    public function forget(Model $model): void
    {
        $morphType = $model->getMorphClass();
        $morphId = (string) $model->getKey();

        DB::transaction(function () use ($morphType, $morphId): void {
            ModerationResult::query()
                ->whereIn('moderation_run_id', function ($query) use ($morphType, $morphId): void {
                    $query->select('id')
                        ->from('auto_moderator_runs')
                        ->where('moderatable_type', $morphType)
                        ->where('moderatable_id', $morphId);
                })
                ->delete();

            ModerationRun::query()
                ->where('moderatable_type', $morphType)
                ->where('moderatable_id', $morphId)
                ->delete();
        });
    }
}
