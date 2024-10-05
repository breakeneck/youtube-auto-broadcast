<?php

namespace App;

use phpseclib3\Net\SSH2;

class Scenario
{
    private $youtube;
    public $camera;
    public $obsPid;
    public function __construct()
    {
        $this->youtube = new \App\Youtube($_ENV['YOUTUBE_AUTH_FILE']);
        $this->camera = new \App\Hikvision($_ENV['HIK_HOST'], $_ENV['HIK_USERNAME'], $_ENV['HIK_PASSWORD']);
    }

    public function startBroadcast($title, $description = '', $lengthMinutes = 120)
    {
        $startTime = date('Y-m-d\TH:i:s\Z');
        $endTime = date('Y-m-d\TH:i:s\Z', strtotime("+ $lengthMinutes minutes"));
        $broadcastId = $this->youtube->createBroadcast($title, $description, $startTime, $endTime, $_ENV['YOUTUBE_PRIVACY']);

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

//    public function wait($minutes)
//    {
//        sleep($minutes * 60);
//    }

    public function startObs()
    {
        $ssh = $this->loginSSH();
        $ssh->exec('vlcout');
        $ssh->exec('systemctl --user start obs-start');
    }

    public function stopObs()
    {
        $ssh = $this->loginSSH();
        $ssh->exec('vlckill');
        $ssh->exec('systemctl --user start obs-stop');
    }

    private function loginSSH()
    {
        $ssh = new SSH2($_ENV['OBS_HOST'], $_ENV['OBS_PORT']);
        if (!$ssh->login($_ENV['OBS_USERNAME'], $_ENV['OBS_PASSWORD'])) {
            throw new \Exception('Login failed');
        }
        return $ssh;
    }

    public function wait($seconds)
    {
        sleep($seconds);
    }
}