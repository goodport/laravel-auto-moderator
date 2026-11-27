<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

final class TestActor extends User
{
    protected $table = 'users';

    protected $guarded = [];
}
