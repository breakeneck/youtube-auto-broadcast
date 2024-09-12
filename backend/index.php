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
        $scenario->wait(5);
        $title = $_POST['title'];
        if (isset($_POST['verse']) && isset($_POST['book'])) {
            $verseParts = explode('.', $_POST['verse']);
            $url = 'https://vedabase.io/ru/library/' . $_POST['book'] . '/' . implode('/', $verseParts);
            $parser = new \App\HtmlParser($url);
            $parser->parseVedabase();

            $about[] = $url;
            $about[] = \App\Utils::BOOKS[$_POST['book']] . ' ' . $_POST['verse'] . "\n" . $parser->sankrit;
            $about[] = "Послівний переклад\n" . $parser->transcribe;
            $about[] = "Переклад\n" . $parser->translation;
            $description = implode("\n\n", $about);
        }
        $broadcastId = $scenario->startBroadcast($title, $description ?? '');
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

app()->run();
