<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $botToken;
    protected $chatId;
    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
    }

    // Gửi OTP qua Telegram
    // Trong TelegramService.php
    public function sendOtp($otpCode, $user = null)
    {
        // Lấy chat_id từ cấu hình
        $chatId = config('services.telegram.chat_id');

        // Chỉnh sửa nội dung tin nhắn cho OTP
        $message = "Your OTP code is: {$otpCode}";

        // Nếu có user, thêm email vào tin nhắn (ẩn phần giữa email)
        if ($user) {
            $email = $user->email;
            $emailParts = explode('@', $email);
            $emailPrefix = $emailParts[0]; // Phần trước dấu '@'
            $emailDomain = $emailParts[1]; // Phần sau dấu '@'

            // Lấy 3-4 ký tự đầu và 4 ký tự cuối của email
            $emailMasked = substr($emailPrefix, 0, 3) . '...@' . $emailDomain;

            // Cập nhật tin nhắn với email đã được chỉnh sửa
            $message .= "\nYour email: {$emailMasked}";
        }

        // URL API Telegram để gửi tin nhắn
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

        // Gửi request tới Telegram API
        $response = Http::post($url, [
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        // Log phản hồi để kiểm tra
        Log::info("Telegram API Response: ", ['response' => $response->json()]);

        // Kiểm tra xem API Telegram có thành công hay không
        if ($response->successful()) {
            return true;
        } else {
            Log::error("Telegram API Error: ", ['error' => $response->json()]);
            return false;
        }
    }








}
