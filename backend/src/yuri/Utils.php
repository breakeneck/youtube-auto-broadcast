<?php

namespace App;

class Utils
{
    static function booksDropDown()
    {
        return [
            'ШБ' => 'ШБ',
            'БГ' => 'БГ',
            'ЧЧ' => 'ЧЧ',
        ];
    }
    static function books()
    {
        return [
            'sb' => 'ШБ',
            'bg' => 'БГ',
            'cc' => 'ЧЧ',
        ];
    }

    static function books_en()
    {
        return array_flip(self::books());
    }

    /**
     * REFERENCE - https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
     * @param $date
     * @param $format
     * @return false|string
     * @throws \Exception
     */
    static function getLocalTimeStr($date, $format)
    {
        $date = $date instanceof \DateTime ? $date->format('Y-m-d') : $date;
        $dateTimeObj = new \DateTime($date, new \DateTimeZone('Europe/Kiev'));
        $str = \IntlDateFormatter::formatObject($dateTimeObj, $format, 'uk');

        // ICU в контейнері не має даних 'uk' і віддає англійську — перекладаємо самі
        // (strtr замінює довші ключі першими, тож повні назви не чіпаються скороченими)
        return strtr((string)$str, self::$uaDateWords);
    }

    private static array $uaDateWords = [
        // дні тижня (EEEE)
        'Monday' => 'понеділок', 'Tuesday' => 'вівторок', 'Wednesday' => 'середа',
        'Thursday' => 'четвер', 'Friday' => 'п\'ятниця', 'Saturday' => 'субота', 'Sunday' => 'неділя',
        // дні тижня скорочено (EEEEEE)
        'Mon' => 'пн', 'Tue' => 'вт', 'Wed' => 'ср', 'Thu' => 'чт', 'Fri' => 'пт', 'Sat' => 'сб', 'Sun' => 'нд',
        'Mo' => 'пн', 'Tu' => 'вт', 'We' => 'ср', 'Th' => 'чт', 'Fr' => 'пт', 'Sa' => 'сб', 'Su' => 'нд',
        // місяці (MMMM) — родовий відмінок, як в датах
        'January' => 'січня', 'February' => 'лютого', 'March' => 'березня', 'April' => 'квітня',
        'May' => 'травня', 'June' => 'червня', 'July' => 'липня', 'August' => 'серпня',
        'September' => 'вересня', 'October' => 'жовтня', 'November' => 'листопада', 'December' => 'грудня',
        // місяці скорочено (MMM)
        'Jan' => 'січ', 'Feb' => 'лют', 'Mar' => 'бер', 'Apr' => 'кві', 'Jun' => 'чер', 'Jul' => 'лип',
        'Aug' => 'сер', 'Sep' => 'вер', 'Oct' => 'жов', 'Nov' => 'лис', 'Dec' => 'гру',
    ];


    static function getYoutubeDescription($book, $verse)
    {
        if (! trim($book) || ! trim($verse)) {
            return '';
        }
        $verseParts = explode('.', $verse);
        $url = 'https://vedabase.io/ru/library/' . self::books_en()[$book] . '/' . implode('/', $verseParts);
        $parser = new \App\HtmlParser($url);
        $parser->parseVedabase();

        $about[] = $book . ' ' . $verse . "\n" . $parser->sankrit;
        $about[] = "\n" . $parser->transcribe;
        $about[] = "\n" . $parser->translation;
        $about[] = $url;

        return  implode("\n\n", $about);
    }
}