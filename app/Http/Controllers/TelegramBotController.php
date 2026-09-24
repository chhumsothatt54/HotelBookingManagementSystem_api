<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends Controller
{
    public function handleWebhook(Request $request)
    {
        // ទទួលទិន្នន័យពី Telegram
        $update = Telegram::commandsHandler(true);
        $chatId = $update->getMessage()->getChat()->getId();
        $text = $update->getMessage()->getText();

        // លក្ខខណ្ឌឆ្លើយតប
        if ($text == '/start') {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'សួស្តី! សូមស្វាគមន៍មកកាន់ AngkorStay Bot។'
            ]);
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'អ្នកបានផ្ញើ៖ ' . $text
            ]);
        }

        return response('OK', 200);
    }
}
