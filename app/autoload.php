<?php

use Doctrine\Common\Annotations\AnnotationRegistry;
use Composer\Autoload\ClassLoader;
use Symfony\Component\Dotenv\Dotenv;

/** @var ClassLoader $loader */
$loader = require __DIR__.'/../vendor/autoload.php';

AnnotationRegistry::registerLoader([$loader, 'loadClass']);

if (!isset($_SERVER['APP_ENV']) && file_exists(dirname(__DIR__).'/.env')) {
	(new Dotenv())->load(dirname(__DIR__).'/.env');
}

return $loader;
