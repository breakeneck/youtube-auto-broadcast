<?php

namespace App;
class GoogleSheet
{
    // Column mapping: Sheet columns A-I correspond to Row properties
    // Sheet: A(empty/manual), B(date), C(time), D(duration), E(dayOfWeek), F(book), G(verse), H(username), I(theme)
    // Row:   A(isManual), B(date), E(dayOfWeek), F(book), G(verse), H(username), I(theme), C(time), D(duration)
    
    const COL_A = 1;  // isManualMode
    const COL_B = 2;  // date
    const COL_C = 3;  // time
    const COL_D = 4;  // duration
    const COL_E = 5;  // dayOfWeek
    const COL_F = 6;  // book
    const COL_G = 7;  // verse
    const COL_H = 8;  // username
    const COL_I = 9;  // theme

    /**
     * Get all rows from the sheet with their row numbers for editing
     * Returns rows sorted in reverse order (newest first), limited to last 30 days
     */
    static function getAllRowsWithRowNumbers($days = 30)
    {
        try {
            $rawRows = self::fetchRows($_ENV['SPREADSHEET_ID'], $_ENV['SHEET_ID'], "!A:I");
        } catch (\Exception $e) {
            return [];
        }

        $rows = [];
        $rowNumber = 2; // Starting from row 2 (row 1 is header)
        
        foreach ($rawRows as $rowArray) {
            // Map sheet columns to row data
            $rows[] = [
                'rowNumber' => $rowNumber,
                'isManualMode' => $rowArray[0] ?? null,
                'date' => $rowArray[1] ?? null,
                'time' => $rowArray[2] ?? null,
                'duration' => $rowArray[3] ?? null,
                'dayOfWeek' => $rowArray[4] ?? null,
                'book' => $rowArray[5] ?? null,
                'verse' => $rowArray[6] ?? null,
                'username' => $rowArray[7] ?? null,
                'theme' => $rowArray[8] ?? null,
            ];
            $rowNumber++;
        }

        // Sort by date descending (newest first), then by time
        usort($rows, function($a, $b) {
            $dateA = $a['date'] ? \DateTime::createFromFormat('d.m.Y', $a['date'], new \DateTimeZone('Europe/Kiev')) : null;
            $dateB = $b['date'] ? \DateTime::createFromFormat('d.m.Y', $b['date'], new \DateTimeZone('Europe/Kiev')) : null;
            
            // Handle null dates - put them at the beginning (most recent)
            if (!$dateA && !$dateB) return 0;
            if (!$dateA) return -1;
            if (!$dateB) return 1;
            
            $dateDiff = $dateB->format('Ymd') - $dateA->format('Ymd');
            if ($dateDiff !== 0) return $dateDiff;
            
            // Same date - sort by time
            $timeA = $a['time'] ?? '';
            $timeB = $b['time'] ?? '';
            return strcmp($timeA, $timeB);
        });

        // Filter to last $days days OR rows with username (even if empty date)
        $now = new \DateTime('now', new \DateTimeZone('Europe/Kiev'));
        $cutoffDate = (new \DateTime('now', new \DateTimeZone('Europe/Kiev')))->modify("-{$days} days");
        
        $rows = array_filter($rows, function($row) use ($cutoffDate, $now) {
            // Show rows that have a date in the range OR have username (even if no date)
            if ($row['username']) return true;
            
            if (!$row['date']) return false;
            try {
                $rowDate = \DateTime::createFromFormat('d.m.Y', $row['date'], new \DateTimeZone('Europe/Kiev'));
                if (!$rowDate) return false;
                // Include if row date is between cutoff and now (inclusive)
                return $rowDate >= $cutoffDate && $rowDate <= $now;
            } catch (Exception $e) {
                return false;
            }
        });

        return array_values($rows);
    }

    /**
     * Get total number of rows in the sheet
     */
    static function getTotalRowCount()
    {
        try {
            $rawRows = self::fetchRows($_ENV['SPREADSHEET_ID'], $_ENV['SHEET_ID'], "!A:A");
            return count($rawRows) + 1; // +1 for header row
        } catch (\Exception $e) {
            return 2; // Default to header + 1 data row
        }
    }

    /**
     * Insert a new row after the specified row number
     * Since Google Sheets API insertDimension is complex, we'll use append for simplicity
     * 
     * @param int $afterRowNumber Insert after this row (for reference only, we append)
     * @param array $data Associative array with row data
     * @return int|false The row number where data was inserted, or false on failure
     */
    static function insertRowAfter($afterRowNumber, $data)
    {
        try {
            error_log("insertRowAfter called with afterRowNumber=" . $afterRowNumber . ", data=" . json_encode($data));
            
            $client = new \Google\Client();
            $client->setApplicationName('YouTube Auto Broadcast');
            $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
            $client->setAuthConfig($_ENV['SHEETS_CREDENTIALS']);

            $service = new \Google\Service\Sheets($client);
            
            // Calculate next row number
            $totalRows = self::getTotalRowCount();
            $nextRow = $totalRows + 1;
            error_log("Total rows: $totalRows, nextRow: $nextRow");
            
            // Prepare row data in sheet column order
            // Sheet: A(isManual), B(date), C(time), D(duration), E(dayOfWeek), F(book), G(verse), H(username), I(theme)
            $rowData = [
                '',                                    // A: isManualMode (empty for auto)
                $data['date'] ?? '',                   // B: date
                $data['time'] ?? '',                   // C: time
                $data['duration'] ?? '',               // D: duration
                '',                                    // E: dayOfWeek (can be auto-calculated)
                $data['book'] ?? '',                   // F: book
                $data['verse'] ?? '',                 // G: verse
                $data['username'] ?? '',               // H: username
                $data['theme'] ?? '',                  // I: theme
            ];
            
            error_log("Row data to insert: " . json_encode($rowData));
            
            $range = $_ENV['SHEET_ID'] . "!A{$nextRow}:I{$nextRow}";
            error_log("Range: $range");
            
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => [$rowData]
            ]);
            
            $params = [
                'valueInputOption' => 'USER_ENTERED'
            ];
            
            $response = $service->spreadsheets_values->update(
                $_ENV['SPREADSHEET_ID'],
                $range,
                $body,
                $params
            );
            
            error_log("Update response: " . json_encode($response->getUpdatedCells()));
            
            return $nextRow;
        } catch (\Exception $e) {
            error_log("GoogleSheet insertRowAfter error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Append a new row to the sheet
     * 
     * @param array $data Associative array with keys: date, time, duration, book, verse, username, theme
     * @return int|false The row number of the new row, or false on failure
     */
    static function appendRow($data)
    {
        try {
            $client = new \Google\Client();
            $client->setApplicationName('YouTube Auto Broadcast');
            $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
            $client->setAuthConfig($_ENV['SHEETS_CREDENTIALS']);

            $service = new \Google\Service\Sheets($client);
            
            // Calculate next row number
            $totalRows = self::getTotalRowCount();
            $nextRow = $totalRows + 1;
            
            // Prepare row data in sheet column order
            // Sheet: A(isManual), B(date), C(time), D(duration), E(dayOfWeek), F(book), G(verse), H(username), I(theme)
            $rowData = [
                '',                                    // A: isManualMode (empty for auto)
                $data['date'] ?? '',                   // B: date
                $data['time'] ?? '',                   // C: time
                $data['duration'] ?? '',               // D: duration
                '',                                    // E: dayOfWeek (can be auto-calculated)
                $data['book'] ?? '',                   // F: book
                $data['verse'] ?? '',                 // G: verse
                $data['username'] ?? '',               // H: username
                $data['theme'] ?? '',                  // I: theme
            ];
            
            $range = $_ENV['SHEET_ID'] . "!A{$nextRow}:I{$nextRow}";
            
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => [$rowData]
            ]);
            
            $params = [
                'valueInputOption' => 'USER_ENTERED'
            ];
            
            $response = $service->spreadsheets_values->append(
                $_ENV['SPREADSHEET_ID'],
                $range,
                $body,
                $params
            );
            
            if ($response->getTableRange()) {
                return $nextRow;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("GoogleSheet appendRow error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update a specific cell in the sheet
     * 
     * @param int $rowNumber Row number in the sheet (1-based, row 1 is header)
     * @param int $columnNumber Column number (1-based, A=1, B=2, etc.)
     * @param mixed $value New value for the cell
     * @return bool Success status
     */
    static function updateCell($rowNumber, $columnNumber, $value)
    {
        try {
            $client = new \Google\Client();
            $client->setApplicationName('YouTube Auto Broadcast');
            $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
            $client->setAuthConfig($_ENV['SHEETS_CREDENTIALS']);

            $service = new \Google\Service\Sheets($client);
            
            // Convert column number to letter (1=A, 2=B, etc.)
            $columnLetter = self::getColumnLetter($columnNumber);
            $range = $_ENV['SHEET_ID'] . "!{$columnLetter}{$rowNumber}:{$columnLetter}{$rowNumber}";
            
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => [[$value]]
            ]);
            
            $params = [
                'valueInputOption' => 'USER_ENTERED'
            ];
            
            $response = $service->spreadsheets_values->update(
                $_ENV['SPREADSHEET_ID'],
                $range,
                $body,
                $params
            );
            
            return $response->getUpdatedCells() === 1;
        } catch (\Exception $e) {
            error_log("GoogleSheet updateCell error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Batch update multiple cells at once
     * 
     * @param array $updates Array of ['row' => int, 'col' => int, 'value' => mixed]
     * @return bool Success status
     */
    static function batchUpdateCells($updates)
    {
        if (empty($updates)) {
            return true;
        }

        try {
            $client = new \Google\Client();
            $client->setApplicationName('YouTube Auto Broadcast');
            $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
            $client->setAuthConfig($_ENV['SHEETS_CREDENTIALS']);

            $service = new \Google\Service\Sheets($client);
            
            $data = [];
            foreach ($updates as $update) {
                $columnLetter = self::getColumnLetter($update['col']);
                $range = $_ENV['SHEET_ID'] . "!{$columnLetter}{$update['row']}:{$columnLetter}{$update['row']}";
                $data[] = new \Google\Service\Sheets\ValueRange([
                    'range' => $range,
                    'values' => [[$update['value']]]
                ]);
            }
            
            $body = new \Google\Service\Sheets\BatchUpdateValuesRequest([
                'valueInputOption' => 'USER_ENTERED',
                'data' => $data
            ]);
            
            $response = $service->spreadsheets_values->batchUpdate(
                $_ENV['SPREADSHEET_ID'],
                $body
            );
            
            return $response->getTotalUpdatedCells() === count($updates);
        } catch (\Exception $e) {
            error_log("GoogleSheet batchUpdateCells error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert column number to letter (1=A, 2=B, ..., 26=Z, 27=AA, etc.)
     */
    private static function getColumnLetter($columnNumber)
    {
        $letter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)) . $letter;
            $columnNumber = (int)($columnNumber / 26);
        }
        return $letter;
    }

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