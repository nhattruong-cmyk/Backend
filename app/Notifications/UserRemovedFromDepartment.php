<?php
namespace App\Notifications;

use App\Models\Department;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class UserRemovedFromDepartment extends Notification
{
    use Queueable;

    protected $department;

    public function __construct(Department $department)
    {
        $this->department = $department;
    }

    public function via($notifiable)
    {
        return ['database']; // Chỉ sử dụng database
    }

    public function toDatabase($notifiable)
    {
        return [
            'department_name' => $this->department->department_name,
            'message' => "Bạn đã bị xóa khỏi phòng ban '{$this->department->department_name}'",

        ];
    }
}