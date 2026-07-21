<?php

use JardisSupport\DotEnv\DotEnv;

error_reporting(E_ALL);
ini_set('display_errors', '1');

$_ENV['APP_ENV'] = 'test';

require_once dirname(__DIR__) . '/vendor/autoload.php';

(new DotEnv())->loadPublic(dirname(__DIR__));
