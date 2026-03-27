<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$state = new \App\SimpleState();

app()->template->config('path', __DIR__ . '/views');

app()->config(['debug' => $_ENV['APP_DEBUG']]);

// Get current scheduled row if broadcast is running
$currentScheduledRow = null;
if ($state->getAttr('id') && $state->getAttr('scheduled_row')) {
    $scheduledData = $state->getAttr('scheduled_row');
    $currentScheduledRow = new \App\Row(...$scheduledData);
}

app()->get('/', function () use ($state, $currentScheduledRow) {
    $lastRows = \App\GoogleSheet::getRowsAfterToday();
//    print_r($lastRows);die;

    echo app()->template->render('index', [
        'state' => $state,
        'lastRows' => $lastRows ?? [],
        'currentScheduledRow' => $currentScheduledRow,
    ]);
});


app()->post('/start', function () use ($state) {

    if (!$state->getAttr('id') ) {
        $scenario = new App\Scenario();
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

    $state->setAttr('id', null);
    $state->setAttr('scheduled_row', null);

    app()->response()->redirect('/');
});

app()->get('/reset', function () use ($state) {
    $state->setAttr('id', null);
    $state->setAttr('scheduled_row', null);

    app()->response()->redirect('/');
});

app()->get('/exitapps', function () use ($state) {
    $scenario = new App\Scenario();
    $scenario->stopObs();

    app()->response()->redirect('/');
});

app()->run();
