<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$state = new \App\SimpleState();

app()->template->config('path', __DIR__ . '/views');

app()->config(['debug' => $_ENV['APP_DEBUG']]);


app()->get('/', function () use ($state) {
    echo app()->template->render('index', [
        'state' => $state
    ]);
});


app()->post('/start', function () use ($state) {

    if (!$state->getAttr('id') ) {
        $scenario = new App\Scenario();
//        $scenario->camera->zoomIn();
//        $scenario->startObs();
        $broadcastId = $scenario->startBroadcast($_POST['title'], 120);
        echo $broadcastId;
//        $scenario->notify($broadcastId);

        $state->setAttr('id', $broadcastId);
    }

    app()->response()->redirect('/');
});

app()->post('/stop', function () use ($state) {
    $broadcastId = $state->getAttr('id');

    $scenario = new App\Scenario();
    $scenario->finishBroadcast($broadcastId);
//    $scenario->stopObs();
//    $scenario->camera->zoomOut();

    $state->setAttr('id', null);

    app()->response()->redirect('/');
});

app()->run();
