<?php

$basedir = __DIR__ . '/../..';
require_once $basedir . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($basedir);
$dotenv->load();

//$response = (new App\Telegram($_ENV['TG_API_TOKEN']))->updates();
//print_r($response->content);

//RD1zZRWtq0M


$scenario = new App\Scenario();
//$scenario->camera->zoomIn();
//$broadcastId = $scenario->startBroadcast('Title', 120);
$scenario->notify('RD1zZRWtq0M');
//$scenario->wait($length);
//$scenario->finishBroadcast($broadcastId);
//$scenario->camera->zoomOut();
