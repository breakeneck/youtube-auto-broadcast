<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$response = (new yuri\Telegram($_ENV['TG_API_TOKEN']))->updates();
print_r($response->content);
