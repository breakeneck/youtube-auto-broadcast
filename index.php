<?php

require_once __DIR__ . '/vendor/autoload.php';

list($scriptName, $streamId, $length) = $argv;
if (! isset($streamId)) {
    die('Please, add youtube stream id as parameter');
}
if (! isset($length)) {
    echo "Length of your broadcast not set. Will be used 5 minutes just for test\n";
    $length = 5;
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$scenario = new \App\Scenario();
$scenario->camera->zoomIn();
$broadcastId = $scenario->startBroadcast($streamId, $length);
$scenario->notify($broadcastId);
$scenario->wait($length);
$scenario->finishBroadcast($broadcastId);
$scenario->camera->zoomOut();
