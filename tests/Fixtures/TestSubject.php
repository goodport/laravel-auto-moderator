<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class TestSubject extends Model
{
    protected $table = 'subjects';

    protected $guarded = [];
}
