<?php

namespace App;

use Google\Service\YouTube\LiveBroadcast;
use Google\Service\YouTube\LiveBroadcastContentDetails;
use Google\Service\YouTube\LiveBroadcastSnippet;
use Google\Service\YouTube\LiveBroadcastStatus;
use Google\Service\YouTube\MonitorStreamInfo;

/**
 * https://developers.google.com/explorer-help/code-samples#php
 */

class Youtube {
    /** @var \Google\Service\YouTube  */
    private $service;
    private bool $isDebug;
    public function __construct($authFilePath, $isDebug = false)
    {
        $this->isDebug = $isDebug;
        $this->service = new \Google_Service_YouTube((new GoogleAuth($authFilePath))->getClient());
    }

    public function createBroadcast($title, $description, $startTime, $endTime, $privacy = 'public'): string
    {
        // Define the $liveBroadcast object, which will be uploaded as the request body.
        /** @var LiveBroadcast $liveBroadcast */
        $liveBroadcast = new \Google_Service_YouTube_LiveBroadcast();

        // Add 'contentDetails' object to the $liveBroadcast object.
        /** @var LiveBroadcastContentDetails $liveBroadcastContentDetails */
        $liveBroadcastContentDetails = new \Google_Service_YouTube_LiveBroadcastContentDetails();
        $liveBroadcastContentDetails->setEnableAutoStart(false);
        $liveBroadcastContentDetails->setEnableAutoStop(false);
        $liveBroadcastContentDetails->setEnableClosedCaptions(true);
        $liveBroadcastContentDetails->setEnableContentEncryption(true);
        $liveBroadcastContentDetails->setEnableDvr(true);
        $liveBroadcastContentDetails->setEnableEmbed(true);
        $liveBroadcastContentDetails->setEnableLowLatency(true);
        /** @var MonitorStreamInfo $monitorStreamInfo */
        $monitorStreamInfo = new \Google_Service_YouTube_MonitorStreamInfo();
        $monitorStreamInfo->setEnableMonitorStream(false);
        $liveBroadcastContentDetails->setMonitorStream($monitorStreamInfo);
        $liveBroadcastContentDetails->setStartWithSlate(true);
        $liveBroadcast->setContentDetails($liveBroadcastContentDetails);

        // Add 'snippet' object to the $liveBroadcast object.
        /** @var LiveBroadcastSnippet $liveBroadcastSnippet */
        $liveBroadcastSnippet = new \Google_Service_YouTube_LiveBroadcastSnippet();
        $liveBroadcastSnippet->setScheduledStartTime($startTime);
        $liveBroadcastSnippet->setScheduledEndTime($endTime);
        $liveBroadcastSnippet->setTitle($title);
        $liveBroadcastSnippet->setDescription($description);
        $liveBroadcast->setSnippet($liveBroadcastSnippet);

        // Add 'status' object to the $liveBroadcast object.
        /** @var LiveBroadcastStatus $liveBroadcastStatus */
        $liveBroadcastStatus = new \Google_Service_YouTube_LiveBroadcastStatus();
        $liveBroadcastStatus->setPrivacyStatus($privacy);
        $liveBroadcastStatus->setSelfDeclaredMadeForKids(false);
        $liveBroadcast->setStatus($liveBroadcastStatus);

        $response = $this->service->liveBroadcasts->insert('snippet,contentDetails,status', $liveBroadcast);

//        print("createBroadcast\n");
        if ($this->isDebug) {
            print_r($response);
        }
        return $response->id;
    }

    public function bindToStream($broadcastId, $streamId)
    {
        // Define service object for making API requests.
        $queryParams = [
            'streamId' => $streamId
        ];

        $response = $this->service->liveBroadcasts->bind($broadcastId, 'id', $queryParams);

//        print("bindToStream\n");

        if ($this->isDebug) {
            print_r($response);
        }

        return $response->id;
    }

    public function goLive($broadcastId)
    {
        $response = $this->service->liveBroadcasts->transition('live', $broadcastId, 'snippet,status');
//        print("GoLive\n");

        if ($this->isDebug) {
            print_r($response);
        }
    }

    public function finish($broadcastId)
    {
        $response = $this->service->liveBroadcasts->transition('complete', $broadcastId, 'snippet,status');
//        print("Finish\n");

        if ($this->isDebug) {
            print_r($response);
        }
    }

    public function listStreams()
    {
        $response = $this->service->liveStreams->listLiveStreams('cdn,status', ['mine' => true]);
//        print("Streams:\n");

        if ($this->isDebug) {
            print_r($response);
        }
        /** @var \Google\Service\YouTube\LiveStream $item */
        foreach ($response->getItems() as $n => $item) {
            $cdn = $item->getCdn();
            $ingestionInfo = $cdn->getIngestionInfo();
            $status = $item->getStatus();
//            $title = $item->getSnippet()->title;
            echo "$n) $item->id [$status->streamStatus] $ingestionInfo->ingestionAddress/$ingestionInfo->streamName ||\n";
        }
    }

    public function createStream()
    {
        $liveStream = new \Google_Service_YouTube_LiveStream();
        $response = $this->service->liveStreams->insert('', $liveStream);
        print_r($response);
    }
}
