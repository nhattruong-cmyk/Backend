<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $verificationCode;

    /**
     * Tạo một instance mới.
     *
     * @param \App\Models\User $user
     * @return void
     */
    public function __construct(User $user, $verificationCode = null)
    {
        $this->user = $user;
        $this->verificationCode = $verificationCode ?? $user->verification_code;  // Nếu không có mã xác nhận, lấy từ user
    }

    /**
     * Build thông điệp.
     *
     * @return \Illuminate\Mail\Mailable
     */
    public function build()
    {
        return $this->view('emails.verify-email')
            ->with([
                'verificationUrl' => route('verification.verify', ['id' => $this->user->id, 'hash' => sha1($this->user->email)]),
                'verificationCode' => $this->verificationCode,  // Truyền mã xác nhận vào view
            ]);
    }

}
