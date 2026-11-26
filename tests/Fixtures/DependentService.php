<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

final class DependentService
{
    public function __construct(public readonly string $name = 'container-resolved') {}
}
