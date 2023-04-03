<?php

namespace App;

use Breakeneck\Http\Request;

class Telegram
{
    private $token;
    public function __construct($token)
    {
        $this->token = $token;
    }
/*
    public function send()
    {
        $INSTANCE_ID = 'YOUR_INSTANCE_ID_HERE';  // TODO: Replace it with your gateway instance ID here
        $CLIENT_ID = "YOUR_CLIENT_ID_HERE";  // TODO: Replace it with your Premium Account client ID here
        $CLIENT_SECRET = "YOUR_CLIENT_SECRET_HERE";   // TODO: Replace it with your Premium Account client secret here
        $CHAT_ID = '';

        $response = (new Request())
            ->json([
                'chat_id' => $CHAT_ID,     // TODO: Specify the number of the group admin
                'message' => 'Your six-pack is on the way!'
            ])
            ->addHeaders([
                'X-WM-CLIENT-ID' => $CLIENT_ID,
                'X-WM-CLIENT-SECRET' => $CLIENT_SECRET
            ])
            ->post('https://api.whatsmate.net/v3/telegram/group/text/message/{id}', ['{id}' => $INSTANCE_ID]);
    }
*/
    public function send($chatId, $text)
    {
        return (new Request())
            ->setData([
                'chat_id' => $chatId,
                'text' => $text
            ])
            ->get('https://api.telegram.org/bot{token}/sendMessage', ['{token}' => $this->token]);
    }
}
