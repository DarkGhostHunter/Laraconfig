<?php

use DarkGhostHunter\Laraconfig\Facades\Setting;

Setting::name('foo')->default('foo_default');

Setting::name('bar')->boolean()->default(false)->bag('bar_bag');

Setting::name('baz')->array()->default(['alpha', 'bravo', 'charlie']);