<?php

namespace App;

const UA_DATE_FORMAT = 'd.m.Y';
class Row {
    public $date;
//    public $dayOfWeek;
    public $verse;
    public $username;
    public $title;
    public $book = 'sb';

    public function __construct($date = null, $dayOfWeek = null, $verse = null, $username = null, $title = null)
    {
        $this->date = $date ? \DateTime::createFromFormat(UA_DATE_FORMAT, $date) : null;
        $this->verse = $verse;
        $this->username = $username;
        $this->title = $title;
    }

    function isToday(): bool
    {
        return $this->date instanceof \DateTime && $this->date->format(UA_DATE_FORMAT) == date(UA_DATE_FORMAT);
    }

    function isWeekTransition($prevRow): bool
    {
        return $prevRow->date instanceof \DateTime && $this->date instanceof \DateTime
            && $this->date->format('N') < $prevRow->date->format('N');
    }

    function dateFormatted()
    {
        return $this->date instanceof \DateTime ? $this->date->format(UA_DATE_FORMAT) : '';
    }

    function dayOfWeek()
    {
        if ($this->date instanceof \DateTime) {
            return Utils::getLocalTimeStr($this->date->format('Y-m-d'), 'EEEEEE');
        }
        return '';
    }
}

class GoogleSheet
{
    static function getRowsAfterToday($count = 7)
    {
        try {
            $rawRows = self::fetchRows($_ENV['SPREADSHEET_ID'], $_ENV['SHEET_ID'], "!A:E");
        } catch (\Exception $e) {
            return [];
        }

        $n = 0;
        $prevRow = null;
        $lastRows = [];

        foreach ($rawRows as $rowArray) {
            $row = new Row(...$rowArray);
            if ($row->isToday()) {
                $n = 1;
            }
            if ($n) {
//                if (($prevRow && $row->isWeekTransition($prevRow)) || $n > $count) {
                if ($n > $count) {
                    break;
                }
                if ($row->verse) {
                    $lastRows[] = $row;
                }
//                if ($row->date) {
//                    $prevRow = $row;
//                }
                $n++;
            }
        }

        return $lastRows;
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