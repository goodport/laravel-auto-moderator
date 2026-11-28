<?php

namespace Goodport\AutoModerator;

use Goodport\AutoModerator\Contracts\Moderatable;
use Goodport\AutoModerator\Moderation\ModerationOutcome;
use Goodport\AutoModerator\Moderation\ModerationOutcomeReader;
use Goodport\AutoModerator\Orchestration\ModerationCaseRunner;
use Goodport\AutoModerator\Orchestration\ModerationDispatcher;
use Goodport\AutoModerator\Orchestration\ModerationRunner;
use Goodport\AutoModerator\Orchestration\RunContextFactory;
use Goodport\AutoModerator\Profiles\ProfileResolver;
use Goodport\AutoModerator\Support\ModerationQueryGuard;
use Goodport\AutoModerator\Support\ModerationResultHandlerRegistry;
use Goodport\AutoModerator\Support\ModerationRunForgetter;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Entry point for the AutoModerator facade.
 */
final class AutoModeratorManager
{
    public function __construct(
        private readonly ProfileResolver $resolver,
        private readonly ModerationRunner $runner,
        private readonly RunContextFactory $contextFactory,
        private readonly ModerationCaseRunner $caseRunner,
        private readonly ModerationOutcomeReader $outcomeReader,
        private readonly ModerationResultHandlerRegistry $handlerRegistry,
        private readonly ModerationDispatcher $dispatcher,
        private readonly ModerationRunForgetter $forgetter,
    ) {}

    public function profile(string $profile): PendingProfile
    {
        return new PendingProfile($profile, $this->resolver, $this->runner, $this->contextFactory);
    }

    public function moderationCase(): PendingModerationCase
    {
        return new PendingModerationCase($this->caseRunner);
    }

    public function dispatch(Model $model, ?int $delaySeconds = null): void
    {
        $this->assertModeratable($model);

        $this->dispatcher->dispatch($model, $delaySeconds);
    }

    public function latest(Model $model, ?string $scenario = null): ?ModerationOutcome
    {
        $this->assertModeratable($model);
        $scenario = ModerationQueryGuard::scenario($scenario);

        return $this->outcomeReader->latest($model, $scenario);
    }

    /**
     * @return array<int|string, ModerationOutcome>
     */
    public function latestFor(iterable $models, ?string $scenario = null): array
    {
        $scenario = ModerationQueryGuard::scenario($scenario);
        $models = $this->validateModerationList($models);

        return $this->outcomeReader->latestFor($models, $scenario);
    }

    /**
     * @return array<int, string>
     */
    public function statuses(): array
    {
        return ModerationOutcome::statuses();
    }

    public function registerHandler(string $modelClass, string $handlerClass): void
    {
        $this->handlerRegistry->register($modelClass, $handlerClass);
    }

    public function forget(Model $model): void
    {
        $this->assertModeratable($model);

        $this->forgetter->forget($model);
    }

    private function assertModeratable(Model $model): void
    {
        if (! $model->exists || $model->getKey() === null) {
            throw new InvalidArgumentException('The moderation model must be persisted.');
        }

        if (! $model instanceof Moderatable) {
            throw new InvalidArgumentException('The moderation model must implement the Moderatable contract.');
        }
    }

    /**
     * @return array<int, Model>
     */
    private function validateModerationList(iterable $models): array
    {
        $validated = [];
        $seen = [];
        $expectedClass = null;
        $expectedMorphClass = null;

        foreach ($models as $model) {
            if (! $model instanceof Model) {
                throw new InvalidArgumentException('The moderation list must contain Eloquent models.');
            }

            $this->assertModeratable($model);

            if ($expectedClass === null) {
                $expectedClass = $model::class;
                $expectedMorphClass = $model->getMorphClass();
            } elseif ($model::class !== $expectedClass || $model->getMorphClass() !== $expectedMorphClass) {
                throw new InvalidArgumentException('The moderation list must contain models of one type.');
            }

            $key = $model->getKey();

            if (array_key_exists($key, $seen)) {
                continue;
            }

            $seen[$key] = true;
            $validated[] = $model;
        }

        return $validated;
    }
}
