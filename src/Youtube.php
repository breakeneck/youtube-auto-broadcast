<?php

namespace App;

/**
 * https://developers.google.com/explorer-help/code-samples#php
 */

class Youtube {
    private \Google_Service_YouTube $service;
    public function __construct()
    {
        $this->service = new \Google_Service_YouTube((new GoogleAuth(false))->getClient());
    }

    public function createBroadcast($title, $startTime, $endTime, $privacy = 'public'): string
    {
        // Define the $liveBroadcast object, which will be uploaded as the request body.
        $liveBroadcast = new \Google_Service_YouTube_LiveBroadcast();

        // Add 'contentDetails' object to the $liveBroadcast object.
        $liveBroadcastContentDetails = new \Google_Service_YouTube_LiveBroadcastContentDetails();
        $liveBroadcastContentDetails->setEnableAutoStart(false);
        $liveBroadcastContentDetails->setEnableAutoStop(true);
        $liveBroadcastContentDetails->setEnableClosedCaptions(true);
        $liveBroadcastContentDetails->setEnableContentEncryption(true);
        $liveBroadcastContentDetails->setEnableDvr(true);
        $liveBroadcastContentDetails->setEnableEmbed(true);
        $liveBroadcastContentDetails->setEnableLowLatency(true);
        $monitorStreamInfo = new \Google_Service_YouTube_MonitorStreamInfo();
        $monitorStreamInfo->setEnableMonitorStream(false);
        $liveBroadcastContentDetails->setMonitorStream($monitorStreamInfo);
        $liveBroadcastContentDetails->setStartWithSlate(true);
        $liveBroadcast->setContentDetails($liveBroadcastContentDetails);

        // Add 'snippet' object to the $liveBroadcast object.
        $liveBroadcastSnippet = new \Google_Service_YouTube_LiveBroadcastSnippet();
        $liveBroadcastSnippet->setScheduledStartTime($startTime);
        $liveBroadcastSnippet->setScheduledEndTime($endTime);
        $liveBroadcastSnippet->setTitle($title);
        $liveBroadcast->setSnippet($liveBroadcastSnippet);

        // Add 'status' object to the $liveBroadcast object.
        $liveBroadcastStatus = new \Google_Service_YouTube_LiveBroadcastStatus();
        $liveBroadcastStatus->setPrivacyStatus($privacy);
        $liveBroadcast->setStatus($liveBroadcastStatus);

        $response = $this->service->liveBroadcasts->insert('snippet,contentDetails,status', $liveBroadcast);

        print("createBroadcast\n");
        print_r($response);
        return $response->id;
    }

    public function bindToStream($broadcastId, $streamId)
    {
        // Define service object for making API requests.
        $queryParams = [
            'streamId' => $streamId
        ];

        $response = $this->service->liveBroadcasts->bind($broadcastId, 'id', $queryParams);

        print("bindToStream\n");
        print_r($response);

        return $response->id;
    }

    public function goLive($broadcastId)
    {
        $response = $this->service->liveBroadcasts->transition('live', $broadcastId, 'snippet,status');
        print("GoLive\n");
        print_r($response);
    }

    public function listStreams()
    {
        $response = $this->service->liveStreams->listLiveStreams('cdn,status', ['mine' => true]);
        print("Streams:\n");
//        print_r($response);
        /** @var \Google\Service\YouTube\LiveStream $item */
        foreach ($response->getItems() as $n => $item) {
            $cdn = $item->getCdn();
            $ingestionInfo = $cdn->getIngestionInfo();
            $status = $item->getStatus();
            echo "$n) $item->id [$status->streamStatus] $ingestionInfo->ingestionAddress/$ingestionInfo->streamName\n";
        }
    }
}
