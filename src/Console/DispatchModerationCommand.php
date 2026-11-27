<?php

namespace Goodport\AutoModerator\Console;

use Goodport\AutoModerator\Contracts\Moderatable;
use Goodport\AutoModerator\Contracts\ModerationSource;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Jobs\RunModerationJob;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues background moderation runs for registered sources.
 */
final class DispatchModerationCommand extends Command
{
    protected $signature = 'auto-moderator:dispatch';

    protected $description = 'Queue background moderation runs for pending models.';

    public function handle(Container $container, Repository $config): int
    {
        $sources = $config->get('auto-moderator.background.sources', []);

        if (! is_array($sources)) {
            throw InvalidConfigurationException::invalidSetting(
                'background.sources',
                'must be an array of source class names.',
            );
        }

        $queue = $this->resolveQueue($config);

        foreach ($sources as $sourceClass) {
            $this->dispatchSource($container, $sourceClass, $queue);
        }

        return self::SUCCESS;
    }

    private function resolveQueue(Repository $config): ?string
    {
        $queue = $config->get('auto-moderator.background.queue');

        if ($queue === null) {
            return null;
        }

        if (! is_string($queue) || trim($queue) === '') {
            throw InvalidConfigurationException::invalidSetting(
                'background.queue',
                'must be a non-empty string or null.',
            );
        }

        return $queue;
    }

    private function dispatchSource(Container $container, mixed $sourceClass, ?string $queue): void
    {
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
            if (! $model instanceof Model || ! $model instanceof Moderatable) {
                throw InvalidConfigurationException::invalidSourceModel($sourceClass);
            }

            $job = new RunModerationJob(
                morphType: $model->getMorphClass(),
                morphId: $model->getKey(),
                scenario: $model->moderationScenario(),
            );

            if ($queue !== null) {
                $job->onQueue($queue);
            }

            dispatch($job);
        }
    }
}
