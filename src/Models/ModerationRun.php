<?php

namespace Goodport\AutoModerator\Models;

use Goodport\AutoModerator\Moderation\ModerationRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One background moderation attempt for a model.
 */
final class ModerationRun extends Model
{
    protected $table = 'auto_moderator_runs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ModerationRunStatus::class,
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function moderatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function results(): HasMany
    {
        return $this->hasMany(ModerationResult::class, 'moderation_run_id');
    }
}
