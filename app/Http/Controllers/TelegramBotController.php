<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TgUser;
use App\Models\Resume;
use Illuminate\Support\Facades\Http;

class TelegramBotController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $data = $request->all();
        $chat_id = $data['message']['chat']['id'];
        $name = $data['message']['from']['first_name'] ?? 'Foydalanuvchi';
        $text = $data['message']['text'] ?? '';

        // Xatolikni to'g'rilash: firstOrCreate metodidan foydalanamiz
        $user = TgUser::firstOrCreate(
            ['chat_id' => $chat_id],
            ['name' => $name]
        );

        if (strpos($text, '/start') !== false) {
            $this->sendMessage($chat_id, "🎉 Botimizga xush kelibsiz, {$name}! Siz rezyumeingizni yaratmoqchimisiz? Barcha imkoniyatlaringizni o'rganing! \n🔍 Rezyume qo'shish uchun /add_resume <rezyume ma'lumotlari> yozing. \n📜 Har qanday yordam uchun /help.");
        }
    }

    public function sendMessage($chat_id, $message)
    {
        $token = '7712034554:AAGNsJrBQDBe46KhD1BICjHaKm3CrlbruTg'; // O'zingizning bot tokeningizni kiriting
        Http::post('https://api.telegram.org/bot' . $token . '/sendMessage', [
            'chat_id' => $chat_id,
            'text' => $message,
        ]);
    }
}
