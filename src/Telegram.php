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



{
    "update_id": 827535379,
    "message": {
        "message_id": 21716,
        "from": {
            "id": 390016459,
            "is_bot": false,
            "first_name": "Yuri",
            "last_name": "D",
            "username": "breakeneck",
            "language_code": "en"
        },
        "chat": {
            "id": -1001197280278,
            "title": "\u0425\u0430\u0440\u0435 \u041a\u0440\u0456\u0448\u043d\u0430 (\u041b\u0443\u0446\u044c\u043a\u0430 \u044f\u0442\u0440\u0430)",
            "type": "supergroup"
        },
        "date": 1681130221,
        "new_chat_participant": {
            "id": 211246197,
            "is_bot": true,
            "first_name": "Telegram Bot Raw",
            "username": "RawDataBot"
        },
        "new_chat_member": {
            "id": 211246197,
            "is_bot": true,
            "first_name": "Telegram Bot Raw",
            "username": "RawDataBot"
        },
        "new_chat_members": [
            {
                "id": 211246197,
                "is_bot": true,
                "first_name": "Telegram Bot Raw",
                "username": "RawDataBot"
            }
        ]
    }
}

*/
    public function message($chatId, $text)
    {
        return (new Request())
            ->setData([
                'chat_id' => $chatId,
                'text' => $text
            ])
            ->get('https://api.telegram.org/bot{token}/sendMessage', ['{token}' => $this->token]);
    }

    /**
     * Retrieving all bot updates, including chat_id and groups added
     * @return \Breakeneck\Http\Response
     */
    public function updates()
    {
        return (new Request())->post('https://api.telegram.org/bot{token}/getUpdates', ['{token}' => $this->token]);
    }
}
