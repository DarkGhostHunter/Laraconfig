<?php

namespace Tests\Dummies;

use DarkGhostHunter\Laraconfig\HasConfig;
use Illuminate\Database\Eloquent\Model;

class DummyModelWithoutSettings extends Model
{
    protected $table = 'users';
}