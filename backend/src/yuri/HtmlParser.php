<?php

namespace App;

use Symfony\Component\DomCrawler\Crawler;

class HtmlParser
{
    protected Crawler $crawler;
    public $sankrit;
    public $transcribe;
    public $translation;
    public function __construct($url)
    {
        $html = file_get_contents($url);
        $this->crawler = new Crawler($html);
    }

    public function parseVedabase()
    {
        $this->sankrit = $this->crawler->filter('.wrapper-verse-text i')->html();
        $this->transcribe = $this->crawler->filter('.wrapper-synonyms .r-synonyms')->html();
        $this->translation = $this->crawler->filter('.wrapper-translation .r-translation')->html();
    }
}