<?php

namespace App;

class Token {
    const AUTH_FILENAME = __DIR__ . '/../../data/auth.json';
    public $data;
    public function __construct()
    {
        if (file_exists(self::AUTH_FILENAME)) {
            $content = file_get_contents(self::AUTH_FILENAME);
            if ($content) {
                $this->data = json_decode($content, true)['data'];
            }
        }
    }

    public function save()
    {
        file_put_contents(self::AUTH_FILENAME, json_encode($this));
    }
}

class GoogleAuth {
    const REDIRECT_URL = 'http://localhost:8001';

    // Create Client Secret here: https://console.cloud.google.com/apis/credentials/oauthclient
    const CLIENT_SECRET_FILE = __DIR__ . '/../secret.json';

    private Token $token;
    private \Google_Client $client;
    private bool $allowWebAuthFlow;
    public function __construct($authFilePath, $allowWebAuthFlow = true)
    {
        $this->token = new Token();
        $this->client = $this->createClient($authFilePath);
        $this->allowWebAuthFlow = $allowWebAuthFlow;
    }

    private function createClient($authFilePath)
    {
        $client = new \Google_Client();
        $client->setApplicationName('Youtube Broadcast Script');
        $client->setScopes([
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtube.force-ssl',
        ]);
        $client->setAuthConfig($authFilePath);
        $client->setAccessType('offline');
        $client->setRedirectUri(self::REDIRECT_URL);
        return $client;
    }

    public function getClient()
    {
        $this->token->data = $this->getAccessToken();
        if (isset($this->token->data['error'])) {
            throw new \Exception($this->token->data['error'].': ' . $this->token->data['error_description']);
        }
        $this->token->save();

        $this->client->setAccessToken($this->token->data);

        return $this->client;
    }

    private function getAccessToken()
    {
        if ($this->token->data) {
            $this->client->setAccessToken($this->token->data);
            if ($this->client->isAccessTokenExpired()) {
                return $this->client->fetchAccessTokenWithRefreshToken();
            }
            else {
                return $this->token->data;
            }
        }

        if (! $this->allowWebAuthFlow) {
            throw new \Exception('Web auth flow is not allowed here');
        }

        printf("Run local server with \nphp -S %s server.php\n", self::REDIRECT_URL);
        printf("Open this link in your browser:\n%s\n", $this->client->createAuthUrl());
        print('Enter verification code: ');
        $authCode = trim(fgets(STDIN));

        return $this->client->fetchAccessTokenWithAuthCode($authCode);
    }
}
