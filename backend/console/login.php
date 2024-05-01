<?php

define('ROOT', __DIR__ . '/..');
require_once ROOT . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(ROOT);
$dotenv->load();

(new \App\GoogleAuth($_ENV['YOUTUBE_AUTH_FILE']))->getClient();
