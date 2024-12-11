<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpdateDepartmentNotification extends Notification
{
    use Queueable;

    protected $department;

    /**
     * Tạo mới thông báo
     */
    public function __construct($department)
    {
        $this->department = $department;
    }

    /**
     * Các kênh gửi thông báo
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Xây dựng nội dung thông báo lưu vào cơ sở dữ liệu
     */
    public function toDatabase($notifiable)
    {
        return [
            'message' => "Phòng ban của bạn đã được cập nhật: {$this->department->department_name}",
            'department_id' => $this->department->id,
        ];
    }

    /**
     * Mảng đại diện cho thông báo (nếu cần gửi qua các kênh khác)
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Phòng ban của bạn đã được cập nhật: {$this->department->department_name}",
        ];
    }
}


