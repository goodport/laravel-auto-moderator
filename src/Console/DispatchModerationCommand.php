<?php

namespace Goodport\AutoModerator\Console;

use Goodport\AutoModerator\Contracts\Moderatable;
use Goodport\AutoModerator\Contracts\ModerationSource;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Orchestration\ModerationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues background moderation runs for registered sources.
 *
 * @internal
 */
final class DispatchModerationCommand extends Command
{
    protected $signature = 'auto-moderator:dispatch';

    protected $description = 'Queue background moderation runs for pending models.';

    public function handle(
        Container $container,
        Repository $config,
        ModerationDispatcher $dispatcher,
    ): int {
        $sources = $config->get('auto-moderator.background.sources', []);

        if (! is_array($sources)) {
            throw InvalidConfigurationException::invalidSetting(
                'background.sources',
                'must be an array of source class names.',
            );
        }

        foreach ($sources as $sourceClass) {
            $this->dispatchSource($container, $sourceClass, $dispatcher);
        }

        return self::SUCCESS;
    }

    private function dispatchSource(
        Container $container,
        mixed $sourceClass,
        ModerationDispatcher $dispatcher,
    ): void {
        if (! is_string($sourceClass) || trim($sourceClass) === '') {
            throw InvalidConfigurationException::invalidSetting(
                'background.sources',
                'must contain non-empty source class names.',
            );
        }

        if (! class_exists($sourceClass)) {
            throw InvalidConfigurationException::invalidSource($sourceClass);
        }

        $source = $container->make($sourceClass);

        if (! $source instanceof ModerationSource) {
            throw InvalidConfigurationException::invalidSource($sourceClass);
        }

        foreach ($source->pending() as $model) {
            if (
                ! $model instanceof Model
                || ! $model instanceof Moderatable
                || ! $model->exists
                || $model->getKey() === null
            ) {
                throw InvalidConfigurationException::invalidSourceModel($sourceClass);
            }

            $dispatcher->dispatch($model);
        }
    }
}
