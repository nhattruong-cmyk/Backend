<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Task;
class TaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $task;
    public $note;

    /**
     * Tạo thông báo email.
     */
    public function __construct(Task $task, $note)
    {
        $this->task = $task;
        $this->note = $note;
    }

    /**
     * Xây dựng email.
     */
    public function build()
    {
        return $this->subject('You have been assigned a new task: ' . $this->task->task_name)
        ->view('emails.task_assigned')
        ->with([
            'task_name' => $this->task->task_name,
            'note' => $this->note,
        ]);


    }
}
