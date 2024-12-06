<?php

namespace App;

const UA_DATE_FORMAT = 'd.m.Y';
const TABLE_DATE_FORMAT = 'd.m';
class Row {
    public $isManualMode;
    public $date;
    public $book;
    public $verse;
    public $username;
//    public $title;

    public function __construct($isManualMode = false, $date = null, $dayOfWeek = null, $book = null, $verse = null, $username = null)
    {
        $this->isManualMode = (bool)$isManualMode;
        $this->date = $date ? \DateTime::createFromFormat(UA_DATE_FORMAT, $date) : null;
        $this->book = $book;
        $this->verse = $verse;
        $this->username = $username;
    }

    public function isValid()
    {
        return $this->book && $this->verse && $this->username;
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

    function dateTableFormat()
    {
        return $this->date instanceof \DateTime
            ? Utils::getLocalTimeStr($this->date, 'EEEEEE, d MMM')
            : '';
    }

    function dayOfWeek()
    {
        if ($this->date instanceof \DateTime) {
            return Utils::getLocalTimeStr($this->date, 'EEEEEE');
        }
        return '';
    }
}