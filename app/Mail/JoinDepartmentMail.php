<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Department;
use App\Models\User;

class JoinDepartmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $department;
    public $confirmationToken; // Thêm biến confirmationToken

    /**
     * Create a new message instance.
     *
     * @param User $user
     * @param Department $department
     * @param string $confirmationToken
     */
    public function __construct(User $user, Department $department, $confirmationToken)
    {
        $this->user = $user;
        $this->department = $department;
        $this->confirmationToken = $confirmationToken; // Gán giá trị token
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Xây dựng URL xác nhận
        $confirmationUrl = url(route('confirmation.accept', ['token' => $this->confirmationToken]));

        return $this->subject('You have been added to a department')
                    ->view('emails.join_department')
                    ->with([
                        'confirmationUrl' => $confirmationUrl, // Truyền confirmationUrl vào view
                    ]);
    }
}
