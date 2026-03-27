<?php

namespace App;
class GoogleSheet
{
    static function getRowsAfterToday($count = 7)
    {
        try {
            // Extended range to include time (H) and duration (I) columns
            $rawRows = self::fetchRows($_ENV['SPREADSHEET_ID'], $_ENV['SHEET_ID'], "!A:I");
        } catch (\Exception $e) {
            return [];
        }

        $n = 0;
        $lastRows = [];

        foreach ($rawRows as $rowArray) {
            $row = new Row(...$rowArray);
            if ($row->isToday()) {
//                print_r($row);
                $n = 1;
            }
            if ($n) {
                if ($n > $count) {
                    break;
                }
                $lastRows[] = $row;
                $n++;
            }
        }

        return $lastRows;
    }

    /**
     * Get all scheduled rows (rows with time set) for today
     */
    static function getScheduledRowsForToday()
    {
        $rows = self::getRowsAfterToday(1);
        
        return array_filter($rows, function($row) {
            return $row->time && $row->isValid();
        });
    }

    /**
     * Get a row that is currently scheduled to start
     */
    static function getRowScheduledToStartNow()
    {
        $rows = self::getScheduledRowsForToday();
        
        foreach ($rows as $row) {
            if ($row->isScheduledNow()) {
                return $row;
            }
        }
        
        return null;
    }

    /**
     * Get a row that is currently running and should stop now
     */
    static function getRowScheduledToStopNow($currentBroadcastId)
    {
        if (!$currentBroadcastId) {
            return null;
        }
        
        // Get the row that started this broadcast from state
        $state = new SimpleState();
        $scheduledRowData = $state->getAttr('scheduled_row');
        
        if (!$scheduledRowData) {
            return null;
        }
        
        $row = new Row(...$scheduledRowData);
        
        if ($row->shouldStopNow()) {
            return $row;
        }
        
        return null;
    }

    static function getTodaysRow()
    {
        return self::getRowsAfterToday(1)[0];
    }

    private static function fetchRows($spreadsheetId, $sheetId, $range)
    {
        $client = new \Google\Client();
        $client->setApplicationName('My PHP Script');
        $client->setScopes(['https://www.googleapis.com/auth/spreadsheets.readonly']);
        $client->setAuthConfig($_ENV['SHEETS_CREDENTIALS']); // Load credentials

        $service = new \Google\Service\Sheets($client);
        $range = $sheetId . $range; // Adjust range based on your sheet data

        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        return $response->getValues();
    }
}