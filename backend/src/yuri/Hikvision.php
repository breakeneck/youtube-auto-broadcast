<?php

namespace App;

use Breakeneck\Http\Request;

class Hikvision
{
    private string $host;
    private string $username;
    private string $password;
    public function __construct(string $host, string $username, string $password)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
    }

    public function execute($method, $url, $data)
    {
        $response = (new Request())
            ->xml()
            ->baseAuth($this->username, $this->password)
            ->send('/ISAPI/System/status');

//        print_r($response->content);
    }

    public function zoomIn()
    {
        return $this->zoom(100);
    }

    public function zoomOut()
    {
        return $this->zoom(-100);
    }

    public function zoom(int $value)
    {
        $response = (new Request())
            ->xml('PTZData', ['zoom' => $value])
            ->baseAuth($this->username, $this->password)
            ->put($this->host . '/ISAPI/PTZCtrl/Channels/1/Continuous');
        $statusCode = $response->content['ResponseStatus']['statusCode'] ?? null;
        return $statusCode == 1;
    }
}
