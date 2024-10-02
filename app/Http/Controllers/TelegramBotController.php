<?php

namespace App\Http\Controllers;

use App\Models\Resume;
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
            session(['awaiting_input' => 'resume']);
            if (strpos($text, '/start') !== false) {
                $this->sendMessage($chat_id, "🎉 Botimizga xush kelibsiz, {$name}! Siz rezyumeingizni yaratmoqchimisiz? Barcha imkoniyatlaringizni o'rganing! \n🔍 Rezyume qo'shish uchun /add_resume <rezyume ma'lumotlari> yozing. \n📜 Har qanday yordam uchun /help.");
            } elseif (str_contains($text, '/add_resume')) {
                $this->sendMessage($chat_id, "📋 Rezyumeingizni qo'shish uchun quyidagi ma'lumotlarni kiriting:\n\n1️⃣ To'liq ismingiz va familiyangiz.\n2️⃣ Ish tajribangiz (Qaysi kompaniyalarda ishlagansiz?).\n3️⃣ Ta'lim darajangiz (Qayerda tahsil olgansiz?).\n4️⃣ Qo'shimcha ko'nikmalar (Qanday malakalarga va sertifikatlarga egasiz?).\n\n✍️ Batafsil ma'lumot kiritganingizga ishonch hosil qiling. Rezyumeingiz qanchalik to'liq bo'lsa, shunchalik ko'proq imkoniyatlarga ega bo'lasiz!");
            } else {
                if(session('awaiting_input') === 'resume')
                {
                    $this->validateUserInput($chat_id, $text);
                }
                $this->sendMessage($chat_id, "nimadur hato ketdi!!!!!!");
            }
        } catch (\Exception $e) {
            Log::error('Xatolik yuz berdi: ' . $e->getMessage());

            $this->sendMessage($chat_id, "❌ Xatolik yuz berdi: " . $e->getMessage());
        }
    }

    public function validateUserInput($chat_id, $user_input)
    {
        $this->sendMessage($chat_id, "\n\n\n🔍 Kiritilgan ma'lumotlaringizni tasdiqlang:\n\n ");
        $this->sendMessage($chat_id, $user_input);
        $this->confirmationButtons($chat_id);
    }

    public function confirmationButtons($chat_id)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        Http::post('https://api.telegram.org/bot' . $token . '/sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'Kiritilgan ma\'lumotlarni tasdiqlaysizmi?',
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => 'Ha'], ['text' => "Yo'q"]],
                ],
                'resize_keyboard' => true, // Tugmalarni o'lchamiga moslashtirish
                'one_time_keyboard' => true // Tugmalarni bosilgandan so'ng yashirish
            ], JSON_THROW_ON_ERROR)
        ]);
    }

    public function saveResume($chat_id, $resume)
    {
        $user = TgUser::query()->where("chat_id" ,$chat_id)->first();
        Resume::create([
            'user_id' => $user->id,
            'resume_data' => $resume,
        ]);
        $this->sendMessage($chat_id, "✅ Sizning rezyumeingiz saqlandi!");
    }
    public function removeReplyKeyboard($chat_id)
    {
        $token = env('TELEGRAM_BOT_TOKEN'); // O'zingizning bot tokeningizni kiriting
        Http::post("https://api.telegram.org/bot" . $token . "/sendMessage", [
            'chat_id' => $chat_id,
            'text' => 'Tugmalar olib tashlandi.',
            'reply_markup' => json_encode([
                'keyboard' => [
                    'remove_keyboard' => true
                ]
            ], JSON_THROW_ON_ERROR)
        ]);
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
