<?php

use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

if (!isset($_SERVER['APP_ENV']) && file_exists(dirname(__DIR__).'/.env')) {
	(new Dotenv())->load(dirname(__DIR__).'/.env');
}
