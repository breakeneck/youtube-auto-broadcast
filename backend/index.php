<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$state = new \App\SimpleState();

app()->template->config('path', __DIR__ . '/views');

app()->config(['debug' => $_ENV['APP_DEBUG']]);


app()->get('/', function () use ($state) {
    $lastRows = \App\GoogleSheet::getRowsAfterToday();

    echo app()->template->render('index', [
        'state' => $state,
        'lastRows' => $lastRows ?? [],
    ]);
});


app()->post('/start', function () use ($state) {

    if (!$state->getAttr('id') ) {
        $scenario = new App\Scenario();
//        $scenario->camera->zoomIn();
        $scenario->startObs();
        $scenario->wait(10);

        $decor = new \App\Decor($_POST);
        $broadcastId = $scenario->startBroadcast($decor->getTitle(), $decor->getDescription());
        $scenario->notify($broadcastId);

        $state->setAttr('id', $broadcastId);
    }

    app()->response()->redirect('/');
});

app()->post('/stop', function () use ($state) {
    $broadcastId = $state->getAttr('id');

    $scenario = new App\Scenario();
    $scenario->finishBroadcast($broadcastId);
    $scenario->stopObs();
//    $scenario->camera->zoomOut();

    $state->setAttr('id', null);

    app()->response()->redirect('/');
});

app()->get('/reset', function () use ($state) {
    $state->setAttr('id', null);

    app()->response()->redirect('/');
});

app()->get('/resetcam', function () use ($state) {
    $broadcastId = $state->getAttr('id');

    if ($broadcastId) {
        $scenario = new App\Scenario();
        $scenario->restartCamera();
    }
    app()->response()->redirect('/');
});

app()->get('/exitapps', function () use ($state) {
    $scenario = new App\Scenario();
    $scenario->stopObs();

    app()->response()->redirect('/');
});

app()->run();
