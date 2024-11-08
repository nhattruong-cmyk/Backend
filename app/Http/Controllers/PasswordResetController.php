<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\TelegramService;

class PasswordResetController extends Controller
{
    // POST /api/password/request-reset
    public function requestPasswordReset(Request $request, TelegramService $telegramService)
    {
        $request->validate([
            'email' => 'nullable|email',
            'phone_number' => 'nullable|digits:10',
        ]);

        $user = null;
        $phoneNumber = $request->phone_number;

        // Chuẩn hóa số điện thoại
        if ($request->has('phone_number')) {
            if (substr($phoneNumber, 0, 1) === '0') {
                $phoneNumber = '84' . substr($phoneNumber, 1);
            } elseif (substr($phoneNumber, 0, 1) !== '8') {
                $phoneNumber = '84' . $phoneNumber;
            }
        }

        // Tìm người dùng qua email hoặc phone_number
        if ($request->has('email')) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->has('phone_number')) {
            $user = User::where('phone_number', $phoneNumber)->first();
        }

        // Kiểm tra xem người dùng có tồn tại không
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Gửi mã qua email, SMS, hoặc Telegram
        if ($request->has('email')) {
            $verificationCode = rand(100000, 999999); // Tạo mã xác thực cho email
            $user->verification_code = $verificationCode;
            $user->verification_code_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            Mail::raw("Your email verification code is: $verificationCode", function ($message) use ($user) {
                $message->to($user->email)->subject('Password Reset Verification Code');
            });
        } elseif ($request->has('phone_number')) {
            $otpCode = rand(100000, 999999); // Tạo mã OTP cho SMS
            $user->otp_code = $otpCode;
            $user->otp_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            // Gửi OTP qua Telegram
            $telegramService->sendOtp($otpCode, $user); // Gửi cả thông tin user vào đây
        }

        return response()->json([
            'message' => 'Verification code sent',
            'contact' => $user->phone_number,  // Hoặc có thể gửi về email nếu cần
        ]);
    }


    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
            'email' => 'nullable|email',
            'phone_number' => 'nullable|digits:10',
        ]);

        $user = null;
        $phoneNumber = $request->phone_number;

        if ($request->has('phone_number')) {
            if (substr($phoneNumber, 0, 1) === '0') {
                $phoneNumber = '84' . substr($phoneNumber, 1);
            } elseif (substr($phoneNumber, 0, 1) !== '8') {
                $phoneNumber = '84' . $phoneNumber;
            }
        }


        if ($request->has('email')) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->has('phone_number')) {
            $user = User::where('phone_number', $phoneNumber)->first();
        }

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Kiểm tra mã
        if ($request->has('email')) {
            $isCodeValid = $user->verification_code == $request->code && now()->lt($user->verification_code_expires_at);
        } else {
            $isCodeValid = $user->otp_code == $request->code && now()->lt($user->otp_expires_at);
        }

        if ($isCodeValid) {
            $user->is_verified_for_reset = true;
            $user->save();
            return response()->json(['message' => 'Code verified, proceed to reset password']);
        } else {
            return response()->json(['message' => 'Invalid or expired code'], 400);
        }
    }

    // Hàm xử lý reset mật khẩu
    public function resetPassword(Request $request)
    {
        $request->validate([
            'new_password' => 'required|string|min:8',
            'email' => 'nullable|email',
            'phone_number' => 'nullable|digits:10',
        ]);

        $user = null;
        $phoneNumber = $request->phone_number;

        if ($request->has('phone_number')) {
            if (substr($phoneNumber, 0, 1) === '0') {
                $phoneNumber = '84' . substr($phoneNumber, 1);
            } elseif (substr($phoneNumber, 0, 1) !== '8') {
                $phoneNumber = '84' . $phoneNumber;
            }
        }

        if ($request->has('email')) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->has('phone_number')) {
            $user = User::where('phone_number', $phoneNumber)->first();
        }

        if (!$user || !$user->is_verified_for_reset) {
            return response()->json(['message' => 'Verification required before resetting password'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->is_verified_for_reset = false;
        $user->otp_code = null;
        $user->verification_code = null;
        $user->save();


        return response()->json(['message' => 'Password reset successful']);
    }

}
