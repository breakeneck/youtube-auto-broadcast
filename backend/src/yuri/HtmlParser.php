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
        $this->parseVedabase();
    }

    public function parseVedabase()
    {
        // New vedabase.io uses av- prefix classes
        try {
            $verseText = $this->crawler->filter('.av-verse_text em')->html();
            $this->sankrit = $this->cleanText($verseText);
        } catch (\Exception $e) {
            $this->sankrit = '';
        }
        
        try {
            $synonyms = $this->crawler->filter('.av-synonyms')->html();
            $this->transcribe = $this->cleanText($synonyms, true);
        } catch (\Exception $e) {
            $this->transcribe = '';
        }
        
        try {
            $translation = $this->crawler->filter('.av-translation')->html();
            $this->translation = $this->cleanText($translation, true);
        } catch (\Exception $e) {
            $this->translation = '';
        }
    }
    
    private function cleanText($html, $preserveLinks = false)
    {
        if ($preserveLinks) {
            // For synonyms/translation, extract text but keep structure
            $text = strip_tags($html, '<br><br/>');
            $text = str_replace(['<br>', '<br />', '<br/>'], "\n", $text);
        } else {
            $text = strip_tags($html);
        }
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}