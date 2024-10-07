<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$state = new \App\SimpleState();

list($action) = array_slice($argv, array_search(basename(__FILE__), $argv) + 1);

switch ($action) {
    case 'start':
        if ($state->getAttr('id') ) {
            return 'Broadcast already running';
        }

        $scenario = new App\Scenario();
        $scenario->startObs();
        $scenario->wait(5);

        $decor = new \App\Decor(\App\GoogleSheet::getTodaysRow());
        $broadcastId = $scenario->startBroadcast($decor->getTitle(), $decor->getDescription());
        $scenario->notify($broadcastId);

        $state->setAttr('id', $broadcastId);
        break;

    case 'stop':
        $broadcastId = $state->getAttr('id');

        $scenario = new App\Scenario();
        $scenario->finishBroadcast($broadcastId);
        $scenario->stopObs();

        $state->setAttr('id', null);
        break;

    case 'reset':
        $state->setAttr('id', null);
        break;
}