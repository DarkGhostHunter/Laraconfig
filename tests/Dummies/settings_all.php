<?php

use DarkGhostHunter\Laraconfig\Facades\Setting;

Setting::name('array')->array();
Setting::name('boolean')->boolean();
Setting::name('collection')->collection();
Setting::name('datetime')->datetime();
Setting::name('float')->float();
Setting::name('integer')->integer();
Setting::name('string')->string();