<?php

namespace App;

class Decor
{
    const BG = 'bg';
    const SB = 'sb';
    const CC = 'cc';
    const BG_UK = 'БГ';
    const SB_UK = 'ШБ';
    const CC_UK = 'ЧЧ';
    const BOOKS = [
        self::SB => self::SB_UK,
        self::BG => self::BG_UK,
        self::CC => self::CC_UK,
    ];
    private $book = '';
    private $verse = '';
    private $username = '';

    private $title = '';

    public function __construct($input = null)
    {
        if ($input instanceof Row) {
            $this->setPost((array)$input);
        }
        elseif (is_array($input)) {
            $this->setPost($input);
        }
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
        $about = [];
        if (! trim($this->book) || ! trim($this->verse)) {
            return '';
        }
        try {
            $verseParts = explode('.', $this->verse);
            $lang = 'ru';
            if ($this->book == self::BG_UK || ($this->book == self::SB_UK && $verseParts[0] <= 3)) {
                $lang = 'uk';
            }
            $url = "https://vedabase.io/$lang/library/" . self::books_en()[$this->book] . '/' . implode('/', $verseParts);
            $parser = new \App\HtmlParser($url);
            $parser->parseVedabase();

            $about[] = $this->book . ' ' . $this->verse . "\n" . $parser->sankrit;
            $about[] = "Послівний переклад\n" . $parser->transcribe;
            $about[] = "Переклад\n" . $parser->translation;
            $about[] = $url;
        } catch (\Exception $e) {
            echo "Error parsing vedabase verse & book for building description: " . $e->getMessage();
        }

        return  implode("\n\n", $about);
    }
}