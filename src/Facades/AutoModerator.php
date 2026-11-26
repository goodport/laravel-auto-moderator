<?php

namespace Goodport\AutoModerator\Facades;

use Goodport\AutoModerator\AutoModeratorManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Goodport\AutoModerator\PendingProfile profile(string $profile)
 *
 * @see AutoModeratorManager
 */
final class AutoModerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AutoModeratorManager::class;
    }
}
