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
        return \IntlDateFormatter::formatObject($dateTimeObj, $format, 'uk');
    }


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
        $about[] = "Послівний переклад\n" . $parser->transcribe;
        $about[] = "Переклад\n" . $parser->translation;
        $about[] = $url;

        return  implode("\n\n", $about);
    }

    static function getYoutubeTitle()
    {

    }
}