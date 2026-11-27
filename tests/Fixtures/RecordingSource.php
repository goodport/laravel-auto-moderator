<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\Moderatable;
use Goodport\AutoModerator\Contracts\ModerationSource;
use Illuminate\Database\Eloquent\Model;

final class RecordingSource implements ModerationSource
{
    /**
     * @var array<int, Model&Moderatable>
     */
    public static array $pending = [];

    public static function reset(): void
    {
        self::$pending = [];
    }

    public function pending(): iterable
    {
        return self::$pending;
    }
}
