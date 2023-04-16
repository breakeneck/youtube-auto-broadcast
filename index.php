<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$state = array_merge(['id' => null], (array)json_decode(file_get_contents('state.json')));

app()->template->config('path', './views');


app()->get('/', function () use ($state) {
    echo app()->template->render('index', [
        'state' => $state
    ]);
});


app()->get('/start', function () use ($state) {

    $scenario = new App\Scenario();
    $scenario->camera->zoomIn();
    $broadcastId = $scenario->startBroadcast(120);
    $scenario->notify($broadcastId);

    $state['id'] = $broadcastId;
    file_put_contents('state.json', $state);

    echo app()->template->render('index', [
        'state' => $state
    ]);
});

app()->get('/stop', function () use ($state) {
    $broadcastId = $state['id'];

    $scenario = new App\Scenario();
    $scenario->finishBroadcast($broadcastId);
    $scenario->camera->zoomOut();

    $state['id'] = null;
    file_put_contents('state.json', $state);

    echo app()->template->render('index', [
        'state' => $state
    ]);
});

app()->run();
