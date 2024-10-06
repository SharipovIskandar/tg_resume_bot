<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;
use App\Models\Resume;
use Illuminate\Http\Request;
use App\Models\TgUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    protected $token = '7712034554:AAGNsJrBQDBe46KhD1BICjHaKm3CrlbruTg';
    protected $telegramApiUrl;

    public function __construct()
    {
        $this->telegramApiUrl = 'https://api.telegram.org/bot' . $this->token . '/';
    }

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
            if (str_contains($text, '/start')) {
                $this->sendMessage($chat_id, "🎉 Botimizga xush kelibsiz, {$name}! Siz rezyumeingizni yaratmoqchimisiz? Barcha imkoniyatlaringizni o'rganing! \n🔍 Rezyume qo'shish uchun /add_resume <rezyume ma'lumotlari> yozing. \n📜 Har qanday yordam uchun /help.");
            } elseif (str_contains($text, '/add_resume')) {
                Redis::set("resume_$chat_id", $text);
                $this->sendMessage($chat_id, "📋 Rezyumeingizni qo'shish uchun quyidagi ma'lumotlarni kiriting:\n\n1️⃣ To'liq ismingiz va familiyangiz.\n2️⃣ Ish tajribangiz (Qaysi kompaniyalarda ishlagansiz?).\n3️⃣ Ta'lim darajangiz (Qayerda tahsil olgansiz?).\n4️⃣ Qo'shimcha ko'nikmalar (Qanday malakalarga va sertifikatlarga egasiz?).\n\n✍️ Batafsil ma'lumot kiritganingizga ishonch hosil qiling. Rezyumeingiz qanchalik to'liq bo'lsa, shunchalik ko'proq imkoniyatlarga ega bo'lasiz!");
            } elseif (str_contains($text, '/help')) {
                $this->sendMessage($chat_id, "📖 **Yordam**\n\n- **/start**: Botni boshlash\n- **/add_resume**: Rezyume qo'shish\n- **/help**: Yordam olish");
            } elseif ($text === 'Ha') {
                $resume_data = Redis::get("resume_$chat_id");
                $this->saveResume($chat_id, $resume_data);
                Redis::del("resume_$chat_id");
            } elseif ($text === "Yo'q") {
                $this->sendMessage($chat_id, "❌ Kiritilgan ma'lumotlar rad etildi.");
                session(['awaiting_input' => null]);
            } else {
                $resume_data = Redis::get("resume_$chat_id");
                if ($resume_data) {
                    $this->validateUserInput($chat_id, $text);
                } else {
                    $this->sendMessage($chat_id, "❓ Qanday yordam kerak? /help komandasini yuboring.");
                }
            }
        } catch (\Exception $e) {
            Log::error('Xatolik yuz berdi: ' . $e->getMessage());

            if (isset($chat_id)) {
                $this->sendMessage($chat_id, "❌ Xatolik yuz berdi: " . $e->getMessage());
            }
        }
    }

    public function validateUserInput($chat_id, $user_input)
    {
        $this->sendMessage($chat_id, "🔍 Kiritilgan ma'lumotlaringizni tasdiqlang:\n\n{$user_input}");
        $this->confirmationButtons($chat_id);
    }

    public function confirmationButtons($chat_id): void
    {
        Http::post($this->telegramApiUrl . 'sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'Kiritilgan ma\'lumotlarni tasdiqlaysizmi?',
            'reply_markup' => json_encode([
                'keyboard' => [
                    [
                        ['text' => 'Ha'],
                        ['text' => "Yo'q"]
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ], JSON_THROW_ON_ERROR)
        ]);
    }

    public function saveResume($chat_id, $resume)
    {
        $user = TgUser::where("chat_id", $chat_id)->first();

        if ($user) {
            $resume = Resume::create([
                'user_id' => $user->id,
                'resume_data' => $resume,
            ]);
            if($resume) {
                $this->sendMessage($chat_id, "✅ Sizning rezyumeingiz saqlandi!");
            }else{
                $this->sendMessage($chat_id, 'Sizning rezyumeingiz saqlanmagan!');
            }
        } else {
            $this->sendMessage($chat_id, "❌ Foydalanuvchi topilmadi.");
        }
    }

    public function removeReplyKeyboard($chat_id)
    {
        Http::post($this->telegramApiUrl . 'sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'Tugmalar olib tashlandi.',
            'reply_markup' => json_encode([
                'remove_keyboard' => true
            ], JSON_THROW_ON_ERROR)
        ]);
    }

    public function sendMessage($chat_id, $message)
    {
        Http::post($this->telegramApiUrl . 'sendMessage', [
            'chat_id' => $chat_id,
            'text' => $message,
        ]);
    }
}
