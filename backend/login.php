<?php


require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

(new \App\GoogleAuth($_ENV['YOUTUBE_AUTH_FILE']))->getClient();
