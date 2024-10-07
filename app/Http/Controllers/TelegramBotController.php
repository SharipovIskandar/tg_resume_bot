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

            if ($data['callback_query']) {
                $callbackQuery = $data['callback_query'];
                $callbackData = $callbackQuery['data'];
                $chat_id = $callbackQuery['from']['id'];

                $this->handleCallbackQuery($callbackQuery);
            }
            $user = TgUser::firstOrCreate(
                ['chat_id' => $chat_id],
                ['name' => $name]
            );

            $user = TgUser::where("chat_id", $chat_id)->first();

            if (str_contains($text, '/start')) {
                $this->sendMessage($chat_id, "🎉 Botimizga xush kelibsiz, {$name}! Siz rezyumeingizni yaratmoqchimisiz? Barcha imkoniyatlaringizni o'rganing! \n🔍 Rezyume qo'shish uchun /add_resume <rezyume ma'lumotlari> yozing. \n📜 Har qanday yordam uchun /help.");
            } elseif (str_contains($text, '/add_resume')) {
                $cleanedText = str_replace('/add_resume', '', $text);
                $this->saveResume($chat_id, $cleanedText);
            } elseif (str_contains($text, '/show_resumes')) {
                if ($user) {

                    $resumes = Resume::query()->where('user_id', $user->id)->get();
                    $this->showResumesWithButtons($chat_id, $resumes);
                }
            } elseif (str_contains($text, '/help')) {
                $this->sendMessage($chat_id, "📖 **Yordam**\n\n- **/start**: Botni boshlash\n- **/add_resume**: Rezyume qo'shish\n- **/help**: Yordam olish");
            }
            if (preg_match('/edit_resume_(\d+)/', $callbackData, $matches)) {
                $resumeIndex = (int)$matches[1] - 1; // 1 dan boshlangani uchun -1
                $this->editResume($chat_id, $resumeIndex);
            } elseif (preg_match('/remove_resume_(\d+)/', $callbackData, $matches)) {
                $resumeIndex = (int)$matches[1] - 1; // 1 dan boshlangani uchun -1
                $this->removeResume($chat_id, $resumeIndex);
            } else {
                $resume_data = Redis::get("resume_$chat_id");
                if ($resume_data) {
                    $this->validateUserInput($chat_id, $text);
                } else {
                    $this->sendMessage($chat_id, "❓ Qanday yordam kerak? /help komandasini yuboring.");
                }
            }
        } catch
        (\Exception $e) {
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
            if ($resume) {
                $this->sendMessage($chat_id, "✅ Sizning rezyumeingiz saqlandi!");
            } else {
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

    public function showResumesWithButtons($chat_id, $resumes)
    {
        $message = "📄 Sizning rezyumelaringiz:\n";
        $buttons = [];

        foreach ($resumes as $index => $resume) {
            $number = $index + 1; // 1 dan boshlash
            $message .= "$number. {$resume->resume_data}\n";
            // Har bir rezyume uchun tugma qo'shish
            $buttons[] = [
                [
                    'text' => "Tahrir qilish $number",
                    'callback_data' => "edit_resume_$number" // Tugma bosilganda muayyan rezyumeni tahrir qilish uchun
                ],
                [
                    'text' => "O'chirish $number",
                    'callback_data' => "remove_resume_$number" // Tugma bosilganda muayyan rezyumeni o'chirish uchun
                ]
            ];
        }

        // Tugmalarni yuborish
        $this->sendMessageWithButtons($chat_id, $message, $buttons);
    }

    public function sendMessageWithButtons($chat_id, $message, $buttons)
    {
        Http::post($this->telegramApiUrl . 'sendMessage', [
            'chat_id' => $chat_id,
            'text' => $message,
            'reply_markup' => json_encode([
                'inline_keyboard' => $buttons, // Inline tugmalar
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function editResume($chat_id, $resumeIndex)
    {
        // Rezyume ma'lumotlarini olish
        $resume = $this->getResumeByIndex($chat_id, $resumeIndex);

        // Foydalanuvchiga rezyume ma'lumotlarini tahrir qilish uchun yuboring
        $this->sendMessage($chat_id, "Iltimos, rezyume ma'lumotlarini tahrir qiling:\n{$resume->resume_data}");

        // Foydalanuvchidan yangi rezyume ma'lumotlarini qabul qilish usuli (masalan, matnli xabar)
    }

    public function removeResume($chat_id, $resumeIndex)
    {
        // Rezyumeni o'chirish
        $this->deleteResumeByIndex($chat_id, $resumeIndex);

        // O'chirish jarayoni tugagach, foydalanuvchiga xabar bering
        $this->sendMessage($chat_id, "Rezyume muvaffaqiyatli o'chirildi.");
    }

    public function sendMessage($chat_id, $message)
    {
        Http::post($this->telegramApiUrl . 'sendMessage', [
            'chat_id' => $chat_id,
            'text' => $message,
            'reply_markup' => json_encode(['remove_keyboard' => true]) // Tugmalarni olib tashlang
        ]);
    }

    public function handleCallbackQuery($callbackQuery)
    {
        $callbackData = $callbackQuery['data'];
        $chat_id = $callbackQuery['from']['id'];

        if (preg_match('/edit_resume_(\d+)/', $callbackData, $matches)) {
            $resumeIndex = (int)$matches[1] - 1; // 1 dan boshlangani uchun -1
            $this->editResume($chat_id, $resumeIndex);
        } elseif (preg_match('/remove_resume_(\d+)/', $callbackData, $matches)) {
            $resumeIndex = (int)$matches[1] - 1; // 1 dan boshlangani uchun -1
            $this->removeResume($chat_id, $resumeIndex);
        }
    }

}
