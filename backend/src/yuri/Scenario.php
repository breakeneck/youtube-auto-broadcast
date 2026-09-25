<?php

namespace App;

use phpseclib3\Net\SSH2;

class Scenario
{
    private $youtube;
    public function __construct()
    {
        $this->youtube = new \App\Youtube($_ENV['YOUTUBE_AUTH_FILE']);
    }

    public function startBroadcast($title, $description = '', $lengthMinutes = 120, $privacy = null)
    {
        $privacy = $privacy ?: $_ENV['YOUTUBE_PRIVACY'];
        $startTime = date('Y-m-d\TH:i:s\Z');
        $endTime = date('Y-m-d\TH:i:s\Z', strtotime("+ $lengthMinutes minutes"));

        $broadcastId = $this->youtube->createBroadcast($title, $description, $startTime, $endTime, $privacy);

        $this->youtube->bindToStream($broadcastId, $_ENV['YOUTUBE_STREAM_ID']);

        // кодер (ffmpeg-cast) пушить з вебки відразу; якщо стрім не став active за ~90 с —
        // значить сигналу з камери/ffmpeg немає, не чекаємо довго, а виходимо з помилкою
        $streamId = $_ENV['YOUTUBE_STREAM_ID'];
        $deadline = time() + 90;
        $streamActive = false;
        while (time() < $deadline) {
            if ($this->youtube->getStreamStatus($streamId) === 'active') {
                $streamActive = true;
                break;
            }
            sleep(3);
        }

        if (!$streamActive) {
            $this->notify($broadcastId, 'УВАГА: стрім не став active за 90 с — схоже, немає сигналу з вебки (ffmpeg-cast). Ефір НЕ запущено.');
            throw new \Exception('Stream not active within 90s');
        }

        try {
            $this->youtube->goLive($broadcastId);
        }
        catch (\Exception $e) {
            // «Redundant transition» означає що ефір уже live — це успіх
            if (!str_contains($e->getMessage(), 'Redundant transition')) {
                $this->notify($broadcastId, 'УВАГА: goLive не вдався: ' . $e->getMessage());
                throw $e;
            }
        }

        return $broadcastId;
    }

    public function finishBroadcast($broadcastId)
    {
        $lifeCycle = $this->youtube->getBroadcastLifeCycle($broadcastId);

        // ефір який так і не вийшов в ефир (created/ready) — завершувати transition('complete') не можна (403)
        if (!in_array($lifeCycle, ['live', 'started', 'liveStarting'], true)) {
            return;
        }

        try {
            $this->youtube->finish($broadcastId);
        }
        catch (\Exception $e) {
            // «Redundant transition» — уже завершений, це успіх
            if (!str_contains($e->getMessage(), 'Redundant transition')) {
                throw $e;
            }
        }
    }

    public function notify($broadcastId, $title, $description = '')
    {
        $message = $title ? "$title\n" : '';
        $message .= "https://www.youtube.com/watch?v=$broadcastId";
        
        if ($description) {
            $message .= "\n\n" . $description;
        }
        $threadId = isset($_ENV['TG_MESSAGE_THREAD_ID']) ? $_ENV['TG_MESSAGE_THREAD_ID'] : null;
        (new \App\Telegram($_ENV['TG_API_TOKEN']))->message($_ENV['TG_CHAT_ID'], $message, $threadId);
    }

//    public function wait($minutes)
//    {
//        sleep($minutes * 60);
//    }

    public function startObs($backend = 'ffmpeg')
    {
        $ssh = $this->loginSSH();
        if ($backend === 'obs') {
            // OBS сам пушить у YouTube (--startstreaming); у ньому сцени з фільтрами (поворот, шумозаглушення)
            $ssh->exec('systemctl --user start obs-start');
        } else {
            // ffmpeg push з /dev/video0 прямо в YouTube (fallback: без фільтрів, простіший і надійніший)
            $ssh->exec('systemctl --user start ffmpeg-cast');
        }
    }

    public function stopObs()
    {
        $ssh = $this->loginSSH();
        // зупиняємо обидва юніти — той, що був обраний на старті; зупинка неактивного — no-op
        $ssh->exec('systemctl --user stop obs-start; pkill -x obs6 2>/dev/null; pkill -x obs 2>/dev/null; true');
        $ssh->exec('systemctl --user stop ffmpeg-cast');
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