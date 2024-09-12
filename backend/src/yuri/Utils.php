<?php

namespace App;

class Utils
{
    const BOOKS = [
        'sb' => 'ШБ',
        'bg' => 'БГ',
        'cc' => 'ЧЧ',
    ];

    /**
     * REFERENCE - https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
     * @param $date
     * @param $format
     * @return false|string
     * @throws \Exception
     */
    static function getLocalTimeStr($date, $format)
    {
        $dateTimeObj = new \DateTime($date, new \DateTimeZone('Europe/Kiev'));
        return \IntlDateFormatter::formatObject($dateTimeObj, $format, 'uk');
    }
}