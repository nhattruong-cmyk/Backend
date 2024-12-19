<?php
namespace App\Mail;

use App\Models\Department;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RemovedUserDepartment extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $department;

    /**
     * Tạo một thể hiện mới của lớp Mailable.
     *
     * @param User $user
     * @param Department $department
     */
    public function __construct(User $user, Department $department)
    {
        $this->user = $user;
        $this->department = $department;
    }

    /**
     * Xây dựng nội dung email.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.user_removed_from_department') // Chỉ định view email
            ->subject('Bạn đã bị xóa khỏi phòng ban: ' . $this->department->department_name) // Tiêu đề email
            ->with([
                'userName' => $this->user->name, // Truyền tên người dùng vào view
                'departmentName' => $this->department->department_name, // Truyền tên phòng ban vào view
            ]);
    }
}