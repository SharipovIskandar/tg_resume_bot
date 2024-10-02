<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TgUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    public function handleWebhook(Request $request)
    {
        try {
            $data = $request->all();
            $chat_id = $data['message']['chat']['id'];
            $name = $data['message']['from']['first_name'] ?? 'Foydalanuvchi';
            $text = $data['message']['text'] ?? '';

            $user = TgUser::firstOrCreate(
                ['chat_id' => $chat_id],
                ['name' => $name]
            );

            if (strpos($text, '/start') !== false) {
                $this->sendMessage($chat_id, "🎉 Botimizga xush kelibsiz, {$name}! Siz rezyumeingizni yaratmoqchimisiz? Barcha imkoniyatlaringizni o'rganing! \n🔍 Rezyume qo'shish uchun /add_resume <rezyume ma'lumotlari> yozing. \n📜 Har qanday yordam uchun /help.");
            }else{
                $this->sendMessage($chat_id, "nimadur hato ketdi!!!!!!");
            }
        } catch (\Exception $e) {
            Log::error('Xatolik yuz berdi: ' . $e->getMessage());

            $this->sendMessage($chat_id, "❌ Xatolik yuz berdi: " . $e->getMessage());
        }
    }

    public function sendMessage($chat_id, $message)
    {
            $token = '7712034554:AAGNsJrBQDBe46KhD1BICjHaKm3CrlbruTg'; // Bot tokenini kiriting
            Http::post('https://api.telegram.org/bot' . $token . '/sendMessage', [
                'chat_id' => $chat_id,
                'text' => $message,
            ]);

    }
}
