<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$state = new \App\SimpleState();

list($action) = array_slice($argv, array_search(basename(__FILE__), $argv) + 1);

switch ($action) {
    case 'start':
        doStart($state);
        break;

    case 'stop':
        doStop($state);
        break;

    case 'check-schedule':
        doCheckSchedule($state);
        break;
}

function doStart($state, $row = null) {
    if ($state->getAttr('id')) {
        echo date('Y-m-d H:i:s') . " START: Broadcast already running\n";
        return;
    }

    if (!$row) {
        $row = \App\GoogleSheet::getTodaysRow();
    }
    
    $decor = new \App\Decor($row);
    echo date('Y-m-d H:i:s') . " START: " . ($decor->row->isManualMode ? 'MANUAL_MODE' : $decor->getTitle()) . "\n";

    if ($decor->row->isManualMode) {
        return;
    }

    $scenario = new App\Scenario();
    $scenario->startObs();
    $scenario->wait(10);

    $duration = $decor->row->duration ?: 120; // Default 120 minutes if not specified
    $broadcastId = $scenario->startBroadcast($decor->getTitle(), $decor->getDescription(), $duration);
    $scenario->notify($broadcastId);

    $state->setAttr('id', $broadcastId);
    
    // Store scheduled row data for stop logic
    if ($decor->row->time && $decor->row->duration) {
        $scheduledData = [
            $decor->row->isManualMode,
            $decor->row->date ? $decor->row->date->format('d.m.Y') : null,
            null, // dayOfWeek
            $decor->row->book,
            $decor->row->verse,
            $decor->row->username,
            $decor->row->theme,
            $decor->row->time,
            $decor->row->duration
        ];
        $state->setAttr('scheduled_row', $scheduledData);
    }
}

function doStop($state) {
    $broadcastId = $state->getAttr('id');

    $scenario = new App\Scenario();
    $scenario->finishBroadcast($broadcastId);
    $scenario->stopObs();

    $state->setAttr('id', null);
    $state->setAttr('scheduled_row', null);
    
    echo date('Y-m-d H:i:s') . " STOP: Broadcast finished\n";
}

function doCheckSchedule($state) {
    $logFile = '/var/www/cron.cast.logs';
    
    // Check if we should start a new broadcast
    if (!$state->getAttr('id')) {
        $row = \App\GoogleSheet::getRowScheduledToStartNow();
        if ($row) {
            $decor = new \App\Decor($row);
            echo date('Y-m-d H:i:s') . " CHECK: Starting scheduled broadcast - " . $decor->getTitle() . "\n";
            file_put_contents($logFile, date('Y-m-d H:i:s') . " CHECK: Starting scheduled broadcast - " . $decor->getTitle() . "\n", FILE_APPEND);
            doStart($state, $row);
            return;
        }
    }
    
    // Check if we should stop the current broadcast
    if ($state->getAttr('id')) {
        $rowToStop = \App\GoogleSheet::getRowScheduledToStopNow($state->getAttr('id'));
        if ($rowToStop) {
            $decor = new \App\Decor($rowToStop);
            echo date('Y-m-d H:i:s') . " CHECK: Stopping scheduled broadcast - " . $decor->getTitle() . "\n";
            file_put_contents($logFile, date('Y-m-d H:i:s') . " CHECK: Stopping scheduled broadcast - " . $decor->getTitle() . "\n", FILE_APPEND);
            doStop($state);
            return;
        }
    }
    
    echo date('Y-m-d H:i:s') . " CHECK: No action needed\n";
}