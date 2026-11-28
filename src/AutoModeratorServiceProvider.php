<?php

namespace Goodport\AutoModerator;

use Goodport\AutoModerator\Console\CleanupStaleModerationRunsCommand;
use Goodport\AutoModerator\Console\DispatchModerationCommand;
use Goodport\AutoModerator\Moderation\ModerationOutcomeReader;
use Goodport\AutoModerator\Orchestration\ModerationCaseRunner;
use Goodport\AutoModerator\Orchestration\ModerationDispatcher;
use Goodport\AutoModerator\Orchestration\ModerationRunner;
use Goodport\AutoModerator\Profiles\ProfileResolver;
use Goodport\AutoModerator\Support\ModerationResultHandlerRegistry;
use Goodport\AutoModerator\Support\ModerationRunForgetter;
use Illuminate\Support\ServiceProvider;

final class AutoModeratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/auto-moderator.php', 'auto-moderator');

        $this->app->singleton(ProfileResolver::class);
        $this->app->singleton(ModerationRunner::class);
        $this->app->singleton(ModerationCaseRunner::class);
        $this->app->singleton(ModerationOutcomeReader::class);
        $this->app->singleton(ModerationResultHandlerRegistry::class);
        $this->app->singleton(ModerationDispatcher::class);
        $this->app->singleton(ModerationRunForgetter::class);
        $this->app->singleton(AutoModeratorManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupStaleModerationRunsCommand::class,
                DispatchModerationCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/auto-moderator.php' => config_path('auto-moderator.php'),
            ], 'auto-moderator-config');
        }
    }
}
