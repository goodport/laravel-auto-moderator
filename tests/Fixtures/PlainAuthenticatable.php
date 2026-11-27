<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

final class PlainAuthenticatable implements Authenticatable
{
    public function __construct(private readonly string $id) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
