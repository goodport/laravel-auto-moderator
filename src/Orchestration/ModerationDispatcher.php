<?php

namespace Goodport\AutoModerator\Orchestration;

use Goodport\AutoModerator\Contracts\Moderatable;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Jobs\RunModerationJob;
use Goodport\AutoModerator\Support\ModerationQueryGuard;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Queues a background moderation job for one model.
 *
 * @internal
 */
final class ModerationDispatcher
{
    public function __construct(private readonly Repository $config) {}

    public function dispatch(Model&Moderatable $model, ?int $delaySeconds = null): void
    {
        if ($delaySeconds !== null && $delaySeconds < 0) {
            throw new InvalidArgumentException('The moderation dispatch delay must be zero or positive.');
        }

        $scenario = ModerationQueryGuard::scenarioName($model->moderationScenario());
        $queue = $this->queue();

        $pending = RunModerationJob::dispatch(
            morphType: $model->getMorphClass(),
            morphId: $model->getKey(),
            scenario: $scenario,
        );

        if ($queue !== null) {
            $pending->onQueue($queue);
        }

        if ($delaySeconds !== null && $delaySeconds > 0) {
            $pending->delay($delaySeconds);
        }
    }

    private function queue(): ?string
    {
        $queue = $this->config->get('auto-moderator.background.queue');

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
}
