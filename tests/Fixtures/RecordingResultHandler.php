<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationResultHandler;
use Goodport\AutoModerator\Moderation\ModerationOutcome;
use Illuminate\Database\Eloquent\Model;

final class RecordingResultHandler implements ModerationResultHandler
{
    /**
     * @var array<int, array{model: Model, outcome: ModerationOutcome}>
     */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function handle(Model $model, ModerationOutcome $outcome): void
    {
        self::$calls[] = [
            'model' => $model,
            'outcome' => $outcome,
        ];
    }
}
