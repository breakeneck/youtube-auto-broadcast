<?php

namespace App;

class GoogleSheet
{
    static function getRowsAfterToday($count = 7)
    {
        $lastRows = [];
        try {
            $client = new \Google\Client();
            $client->setApplicationName('My PHP Script');
            $client->setScopes(['https://www.googleapis.com/auth/spreadsheets.readonly']);
            $client->setAuthConfig($_ENV['SHEETS_CREDENTIALS']); // Load credentials

            $service = new \Google\Service\Sheets($client);
            $range = $_ENV['SHEET_ID'] . "!A:E"; // Adjust range based on your sheet data

            $response = $service->spreadsheets_values->get($_ENV['SPREADSHEET_ID'], $range);
            $rows = $response->getValues();

            $dateTimeObj = new \DateTime('now', new \DateTimeZone('Europe/Kiev'));
            $formatted = \IntlDateFormatter::formatObject($dateTimeObj, 'EEEE dd.MM.Y', 'uk');
            echo "Сьогодні $formatted\n";
//    print_r($rows);
            $n = 0;
            $prevDate = null;
            foreach ($rows as $row) {
                if ($row[0] == date('d.m.Y')) {
                    $n = 1;
                }
                if ($n) {
                    if ($prevDate && \DateTime::createFromFormat($row[0], 'd.m.Y')->format('N') < $prevDate->format('N')) {
                        break;
                    }
                    if (isset($row[2]) && $row[2]) {
                        $lastRows[] = $row;
                    }
                    if ($n > $count) {
                        break;
                    }
                    if ($row[0]) {
                        $prevDate = \DateTime::createFromFormat($row[0], 'd.m.Y');
                    }
                    $n++;
                }
            }
        }
        catch (\Exception $exception) {

        }
        return $lastRows;
    }
}