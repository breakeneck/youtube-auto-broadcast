<?php

namespace App;

class Decor
{
    const BOOKS = [
        'sb' => 'ШБ',
        'bg' => 'БГ',
        'cc' => 'ЧЧ',
    ];
    private $book = '';
    private $verse = '';
    private $username = '';

    private $title = '';

    public function __construct($input = null)
    {
        if ($input instanceof Row) {
            $this->setRow($input);
        }
        elseif (is_array($input)) {
            $this->setPost($input);
        }
    }

    public function setRow($row)
    {

    }

    public function setPost($post)
    {
        if (isset($post['title']) && trim($post['title']) != '') {
            $this->title = $post['title'];
        }
        else {
            $this->book = $post['book'];
            $this->verse = $post['verse'];
            $this->username = $post['username'];
        }
    }

    static function booksDropDown()
    {
        return array_values(self::BOOKS);
    }

    static function books_en()
    {
        return array_flip(self::BOOKS);
    }
    public function getTitle()
    {
        return $this->title ?: "$this->book $this->verse - $this->username";
    }

    public function getDescription()
    {
        if (! trim($this->book) || ! trim($this->verse)) {
            return '';
        }
        $verseParts = explode('.', $this->verse);
        $url = 'https://vedabase.io/ru/library/' . self::books_en()[$this->book] . '/' . implode('/', $verseParts);
        $parser = new \App\HtmlParser($url);
        $parser->parseVedabase();

        $about[] = $this->book . ' ' . $this->verse . "\n" . $parser->sankrit;
        $about[] = "Послівний переклад\n" . $parser->transcribe;
        $about[] = "Переклад\n" . $parser->translation;
        $about[] = $url;

        return  implode("\n\n", $about);
    }
}