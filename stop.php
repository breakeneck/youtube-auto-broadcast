<?php



require_once __DIR__ . '/vendor/autoload.php';

list($scriptName, $broadcastId) = $argv;

if (! isset($broadcastId)) {
    echo "Please add broadcast id as argv\n";
    $length = 5;
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$scenario = new yuri\Scenario();
$scenario->finishBroadcast($broadcastId);
$scenario->camera->zoomOut();
