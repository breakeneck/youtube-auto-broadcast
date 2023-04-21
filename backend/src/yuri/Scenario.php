<?php

namespace App;

class Scenario
{
    private $youtube;
    public $camera;
    public function __construct()
    {
        $this->youtube = new \App\Youtube($_ENV['YOUTUBE_AUTH_FILE']);
        $this->camera = new \App\Hikvision($_ENV['HIK_HOST'], $_ENV['HIK_USERNAME'], $_ENV['HIK_PASSWORD']);
    }

    public function startBroadcast($title, $minutes)
    {
        $startTime = date('Y-m-d\TH:i:s\Z');
        $endTime = date('Y-m-d\TH:i:s\Z', strtotime("+ $minutes minutes"));
        $broadcastId = $this->youtube->createBroadcast($title, $startTime, $endTime, $_ENV['YOUTUBE_PRIVACY']);

        $this->youtube->bindToStream($broadcastId, $_ENV['YOUTUBE_STREAM_ID']);

        $this->youtube->goLive($broadcastId);

        return $broadcastId;
    }

    public function finishBroadcast($broadcastId)
    {
        $this->youtube->finish($broadcastId);
    }

    public function notify($broadcastId)
    {
        $message = "https://www.youtube.com/watch?v=$broadcastId";
        (new \App\Telegram($_ENV['TG_API_TOKEN']))->message($_ENV['TG_CHAT_ID'], $message);
    }

    public function wait($minutes)
    {
        sleep($minutes * 60);
    }
}
