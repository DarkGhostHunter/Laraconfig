<?php

namespace Tests\Dummies;

use DarkGhostHunter\Laraconfig\HasConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static static find($int)
 */
class DummyModel extends Model
{
    use HasConfig;

    protected $table = 'users';
}