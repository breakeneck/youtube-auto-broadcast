<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$state = new \App\SimpleState();

app()->template->config('path', __DIR__ . '/views');

app()->config(['debug' => $_ENV['APP_DEBUG']]);


app()->get('/', function () use ($state) {

// Replace with your downloaded JSON credentials file path
//    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/data/youtubebroadcastersheets-4974f2784de2.json');
    $credentialsPath = __DIR__ . '/data/youtubebroadcastersheets-4974f2784de2.json';

    $spreadsheetId = '1ai7ze0rTnOR6wAOkSl3Lc8x0v-M1cO_qJfE7Bvv3C5I'; // Replace with your spreadsheet ID
//    $sheetId = '219376182';
    $sheetId = 'SB';

    $client = new Google\Client();
    $client->setApplicationName('My PHP Script');
    $client->setScopes(['https://www.googleapis.com/auth/spreadsheets.readonly']);
    $client->setAuthConfig($credentialsPath); // Load credentials

    $service = new Google\Service\Sheets($client);
//    $response = $service->spreadsheets->get($spreadsheetId); // Get spreadsheet info
//    $sheets = [];
//    foreach ($response->getSheets() as $sheetData) {
//        print_r($sheetData['properties']);
////        $sheets[] = print_r($sheetData['properties'])['id']; // Extract sheet title
//    }
//
//    echo "Available sheets:\n";
//    echo implode(", ", $sheets) . "\n";

    $range = "$sheetId!A:E"; // Adjust range based on your sheet data

    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $rows = $response->getValues();
//    print_r($rows);
    $n = 0;
    foreach ($rows as $row) {
        if ($row[0] == date('d.m.Y')) {
            $n = 1;
        }
        if ($n && $row[1] === 'нд') {
            break;
        }
        if ($n) {
            $lastRows[] = $row;
        }
    }

//    $lastRows = array_slice($rows, -10); // Get last 10 rows

    echo app()->template->render('index', [
        'state' => $state,
        'lastRows' => $lastRows,
    ]);
});


app()->post('/start', function () use ($state) {

    if (!$state->getAttr('id') ) {
        $scenario = new App\Scenario();
//        $scenario->camera->zoomIn();
//        $scenario->startObs();
        $broadcastId = $scenario->startBroadcast($_POST['title'], 120);
        echo $broadcastId;
        $scenario->notify($broadcastId);

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
