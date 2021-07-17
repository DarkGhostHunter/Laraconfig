<?php

use DarkGhostHunter\Laraconfig\Facades\Setting;

Setting::name('color')->default('red');

Setting::name('notify')->boolean()->default('false')->bag('admins');

Setting::name('birthday')->datetime();