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

/**
 * Editor page - Google Sheets-like interface for editing schedule
 */
app()->get('/editor', function () {
    $rows = \App\GoogleSheet::getAllRowsWithRowNumbers();
    
    echo app()->template->render('editor', [
        'rows' => $rows,
    ]);
});

/**
 * API endpoint for updating a single cell
 * Expects JSON: { "row": int, "col": int, "value": mixed }
 */
app()->post('/api/sheet/update-cell', function () {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['row']) || !isset($input['col']) || !isset($input['value'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    $success = \App\GoogleSheet::updateCell(
        (int)$input['row'],
        (int)$input['col'],
        $input['value']
    );
    
    echo json_encode(['success' => $success]);
});

/**
 * API endpoint for inserting a new row after the specified row
 * Expects JSON: { "afterRow": int, "data": { "date": string, "time": string, ... } }
 */
app()->post('/api/sheet/insert-row', function () {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['afterRow']) || !isset($input['data'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    $rowNumber = \App\GoogleSheet::insertRowAfter(
        (int)$input['afterRow'],
        $input['data']
    );
    
    if ($rowNumber) {
        echo json_encode(['success' => true, 'rowNumber' => $rowNumber]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to insert row']);
    }
});

/**
 * API endpoint for batch updating cells
 * Expects JSON: { "updates": [{ "row": int, "col": int, "value": mixed }, ...] }
 */
app()->post('/api/sheet/batch-update', function () {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['updates']) || !is_array($input['updates'])) {
        echo json_encode(['success' => false, 'error' => 'Missing updates array']);
        return;
    }
    
    $success = \App\GoogleSheet::batchUpdateCells($input['updates']);
    
    echo json_encode(['success' => $success]);
});

/**
 * API endpoint for adding a new row
 * Expects JSON: { "date": string, "time": string, "duration": string, "book": string, "verse": string, "username": string, "theme": string }
 */
app()->post('/api/sheet/append-row', function () {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $rowNumber = \App\GoogleSheet::appendRow($input);
    
    if ($rowNumber) {
        echo json_encode(['success' => true, 'rowNumber' => $rowNumber]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to append row']);
    }
});

app()->post('/start', function () use ($state) {

    if (!$state->getAttr('id') ) {
        $scenario = new App\Scenario();
        $scenario->startObs();
        $scenario->wait(10);

        $decor = new \App\Decor($_POST);
        $broadcastId = $scenario->startBroadcast($decor->getTitle(), $decor->getDescription());
        $scenario->notify($broadcastId, $decor->getDescription());

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
