<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAddedToDepartment extends Notification
{
    use Queueable;
    protected $department;
    // Nhận thông tin phòng ban
    public function __construct($department)
    {
        $this->department = $department;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    // Gửi thông báo qua kênh mail
    public function via($notifiable)
    {
        return ['database']; // Bạn có thể gửi qua database và mail
    }

    // Xây dựng thông báo
    public function toDatabase($notifiable)
    {
        return [
            'message' => "Bạn đã được thêm vào phòng ban '{$this->department->department_name}'",
            'department_id' => $this->department->id,
        ];
    }


    /**
     * Get the mail representation of the notification.
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->line('The introduction to the notification.')
    //         ->action('Notification Action', url('/'))
    //         ->line('Thank you for using our application!');
    // }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
