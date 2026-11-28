<?php

namespace Goodport\AutoModerator\Support;

use Goodport\AutoModerator\Contracts\Moderatable;
use Goodport\AutoModerator\Contracts\ModerationResultHandler;
use Goodport\AutoModerator\Moderation\ModerationOutcome;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Stores and resolves application result handlers.
 *
 * @internal
 */
final class ModerationResultHandlerRegistry
{
    /**
     * @var array<string, string>
     */
    private array $handlers = [];

    public function __construct(private readonly Container $container) {}

    public function register(string $modelClass, string $handlerClass): void
    {
        $resolvedModelClass = Relation::getMorphedModel($modelClass) ?? $modelClass;

        if (
            ! class_exists($resolvedModelClass)
            || ! is_subclass_of($resolvedModelClass, Model::class)
            || ! is_subclass_of($resolvedModelClass, Moderatable::class)
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'The moderation handler model "%s" must be a Moderatable Eloquent model or a mapped morph alias.',
                    $modelClass,
                ),
            );
        }

        if (
            ! class_exists($handlerClass)
            || ! is_subclass_of($handlerClass, ModerationResultHandler::class)
        ) {
            throw new InvalidArgumentException(
                sprintf('The moderation handler "%s" must implement ModerationResultHandler.', $handlerClass),
            );
        }

        $this->handlers[$modelClass] = $handlerClass;
    }

    public function handle(Model $model, ModerationOutcome $outcome): void
    {
        $handlerClass = $this->handlers[$model::class]
            ?? $this->handlers[$model->getMorphClass()]
            ?? null;

        if ($handlerClass === null) {
            return;
        }

        $handler = $this->container->make($handlerClass);

        if (! $handler instanceof ModerationResultHandler) {
            throw new InvalidArgumentException(
                sprintf('The moderation handler "%s" must implement ModerationResultHandler.', $handlerClass),
            );
        }

        $handler->handle($model, $outcome);
    }
}
