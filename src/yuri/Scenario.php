<?php

namespace yuri;

class Scenario
{
    private $youtube;
    public $camera;
    public function __construct()
    {
        $this->youtube = new \yuri\Youtube($_ENV['YOUTUBE_AUTH_FILE']);
        $this->camera = new \yuri\Hikvision($_ENV['HIK_HOST'], $_ENV['HIK_USERNAME'], $_ENV['HIK_PASSWORD']);
    }

    public function startBroadcast($length)
    {
        $startTime = date('Y-m-d\TH:i:s\Z');
        $endTime = date('Y-m-d\TH:i:s\Z', strtotime("+ $length minutes"));
        $broadcastId = $this->youtube->createBroadcast('Test broadcast', $startTime, $endTime, $_ENV['YOUTUBE_PRIVACY']);

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
        (new \yuri\Telegram($_ENV['TG_API_TOKEN']))->message($_ENV['TG_CHAT_ID'], $message);
    }

    public function wait($minutes)
    {
        sleep($minutes * 60);
    }
}