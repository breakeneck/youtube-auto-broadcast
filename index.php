<?php

require_once __DIR__ . '/vendor/autoload.php';

if (! isset($argv[1])) {
    die('Please, add youtube stream id as parameter');
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


$hik = new \App\Hikvision($_ENV['HIK_HOST'], $_ENV['HIK_USERNAME'], $_ENV['HIK_PASSWORD']);
if ($hik->zoomIn()) {
    echo "operation successfully\n";
}

list($startTime, $endTime) = [date('Y-m-d\TH:i:s\Z'), date('Y-m-d\TH:i:s\Z', strtotime('+5 minutes'))];

$youtube = new \App\Youtube();
$broadcastId = $youtube->createBroadcast('Test broadcast', $startTime, $endTime, $_ENV['YOUTUBE_PRIVACY']);
$youtube->bindToStream($broadcastId , $argv[1]);
$youtube->goLive($broadcastId);

$link = "https://www.youtube.com/watch?v=$broadcastId\n";
echo "Broadcast created: $link\n";

(new \App\Telegram($_ENV['TG_API_TOKEN']))->send($_ENV['TG_CHAT_ID'], "$broadcastId");
