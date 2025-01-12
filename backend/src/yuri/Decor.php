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

    const CC_LILA = [
        '1' => 'adi',
        '2' => 'madhya',
        '3' => 'antya'
    ];
    const BOOKS = [
        self::SB => self::SB_UK,
        self::BG => self::BG_UK,
        self::CC => self::CC_UK,
    ];
    
    public Row $row;

    private $title = '';

    public function __construct($input = null)
    {
        if ($input instanceof Row) {
            $this->row = $input;
        }
        elseif (is_array($input)) {
            $this->setPost($input);
        }
    }

    public function setPost($post)
    {
        $this->row = new Row();
        if (isset($post['title']) && trim($post['title']) != '') {
            $this->title = $post['title'];
        }
        else {
            $this->row->book = $post['book'];
            $this->row->verse = $post['verse'];
            $this->row->username = $post['username'];
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
        return $this->title ?: "{$this->row->book} {$this->row->verse} - {$this->row->username}" . ($this->row->theme ? " - {$this->row->theme}" : '');
    }

    public function getDescription()
    {
        $about = [];
        if (! trim($this->row->book) || ! trim($this->row->verse)) {
            return '';
        }
        try {
            $url = $this->getVedabaseUrl();
            $parser = new \App\HtmlParser($url);
            $parser->parseVedabase();

            $about[] = $this->row->book . ' ' . $this->row->verse . "\n" . $parser->sankrit;
            $about[] = "Послівний переклад\n" . $parser->transcribe;
            $about[] = "Переклад\n" . $parser->translation;
            $about[] = $url;
        } catch (\Exception $e) {
            echo "Error parsing VedaBase verse & book for building description: " . $e->getMessage();
        }

        return  implode("\n\n", $about);
    }

    public function getVedabaseUrl()
    {
        $verseParts = explode('.', $this->row->verse);
        $lang = 'ru';
        if ($this->row->book == self::BG_UK || ($this->row->book == self::SB_UK && $verseParts[0] <= 3)) {
            $lang = 'uk';
        }
        if ($this->row->book == self::CC_UK) {
            $verseParts[0] = self::CC_LILA[$verseParts[0]];
        }
        return "https://vedabase.io/$lang/library/" . self::books_en()[$this->row->book] . '/' . implode('/', $verseParts);
    }
}