<?php

namespace App;
class GoogleSheet
{
    static function getRowsAfterToday($count = 7)
    {
        try {
            // Range A:I - user data structure (added column I for theme)
            $rawRows = self::fetchRows($_ENV['SPREADSHEET_ID'], $_ENV['SHEET_ID'], "!A:I");
        } catch (\Exception $e) {
            return [];
        }

        $n = 0;
        $lastRows = [];

        foreach ($rawRows as $rowArray) {
            // Reorder columns to match Row constructor:
            // Row: isManualMode, date, dayOfWeek, book, verse, username, theme, time, duration
            // Sheet: empty, date, time, duration, dayOfWeek, book, verse, username, theme
            $reordered = [
                $rowArray[0] ?? null,           // A: isManualMode
                $rowArray[1] ?? null,           // B: date
                $rowArray[4] ?? null,           // E: dayOfWeek
                $rowArray[5] ?? null,           // F: book
                $rowArray[6] ?? null,           // G: verse
                $rowArray[7] ?? null,           // H: username
                $rowArray[8] ?? null,           // I: theme (optional)
                $rowArray[2] ?? null,           // C: time
                $rowArray[3] ?? null,           // D: duration
            ];
            
            $row = new Row(...$reordered);
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