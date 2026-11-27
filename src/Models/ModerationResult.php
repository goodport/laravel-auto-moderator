<?php

namespace Goodport\AutoModerator\Models;

use Goodport\AutoModerator\Moderation\ModerationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One check result saved by a background moderation run.
 */
final class ModerationResult extends Model
{
    protected $table = 'auto_moderator_results';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => ModerationState::class,
            'findings' => 'array',
            'details' => 'array',
        ];
    }

    public function moderationRun(): BelongsTo
    {
        return $this->belongsTo(ModerationRun::class, 'moderation_run_id');
    }
}
