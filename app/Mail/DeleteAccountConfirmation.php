<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeleteAccountConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function build()
    {
        return $this->view('emails.delete_account_confirmation')
            ->with([
                'userName' => $this->user->name,
                'confirmationUrl' => route('account.confirmDelete', ['token' => $this->user->delete_token])
            ]);
    }

}

