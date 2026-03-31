<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$appDebug = $_SERVER['APP_DEBUG'] ?? false;
if ($appDebug === true || $appDebug === '1') {
    umask(0000);
}
