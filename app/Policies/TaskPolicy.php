<?php

namespace App\Policies;

use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class TaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin có thể xem tất cả task
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem tất cả task
        if ($user->role_id === 2) {
            return true;
        }

        // Staff chỉ có thể xem task mà họ thuộc về hoặc họ đã tạo
        if ($user->role_id === 3) {
            // Kiểm tra xem user có thuộc phòng ban nào không
            if ($user->departments()->exists()) {
                return true;
            }

            // Kiểm tra xem user có tạo ra task nào không
            return DB::table('tasks')->where('user_id', $user->id)->exists();
        }

        // Mặc định không cho phép xem project
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task): bool
    {
        // Admin có thể xem bất kỳ project nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem project mà họ quản lý
        if ($user->role_id === 2) {
            return true;
        }

        // Staff chỉ có thể xem project mà họ thuộc về hoặc họ đã tạo
        if ($user->role_id === 3) {
            // Kiểm tra xem user có thuộc phòng ban nào không
            if ($user->departments()->exists()) {
                return true;
            }

            // Kiểm tra xem user có tạo ra project nào không
            return DB::table('tasks')->where('user_id', $user->id)->exists();
        }

        // Nếu không, từ chối quyền
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admin có quyền tạo task cho bất kỳ dự án nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có quyền tạo task cho dự án mà họ quản lý
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có quyền tạo task mới (không cần kiểm tra dự án hoặc department)
        if ($user->role_id === 3) {
            return true;
        }

        // Mặc định, nếu không thỏa mãn bất kỳ điều kiện nào, không có quyền tạo task
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Task $task)
    {
        // Admin có thể cập nhật tất cả các task
        if ($user->role_id === 1) {
            return true;
        }

        // Manager chỉ có thể cập nhật task thuộc dự án mà họ quản lý
        if ($user->role_id === 2 && $task->projects->contains('manager_id', $user->id)) {
            return true;
        }

        // Staff chỉ có thể cập nhật task nếu task đã được phân công cho họ
        if ($user->role_id === 3) {
            return true;
        }

        // Nếu không thỏa mãn bất kỳ điều kiện nào
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        // Admin có thể xóa bất kỳ phòng ban nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa phòng ban nếu họ quản lý phòng ban đó
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xóa mềm phòng ban nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && !is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            $task->delete(); // Xóa mềm phòng ban
            return true;
        }
        // Staff có thể xóa mềm phòng ban nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            $task->delete(); // Xóa mềm phòng ban
            return true;
        }

        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }


    /**
     * Determine whether the user can restore the model.
     */
    // Phân quyền cho khôi phục task

    public function restore(User $user, Task $task): bool
    {
        // Admin có thể xóa bất kỳ phòng ban nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa phòng ban nếu họ quản lý phòng ban đó
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xóa mềm phòng ban nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && !is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            $task->restore(); // Xóa mềm phòng ban
            return true;
        }

        // Staff có thể xóa mềm phòng ban nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            $task->restore(); // Xóa mềm phòng ban
            return true;
        }

        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }

    // Phân quyền cho xóa cứng task
    public function forceDelete(User $user, Task $task): bool
    {
        // Admin có thể xóa vĩnh viễn bất kỳ task nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa vĩnh viễn task trong dự án mà họ quản lý
        if ($user->role_id === 2) {
            foreach ($task->projects as $project) {
                if ($project->manager_id === $user->id) {
                    return true;
                }
            }
        }

        // Staff không có quyền xóa vĩnh viễn task
        return false;
    }

    // TaskPolicy.php

    public function moveTasksToAnotherWorktime(User $user): bool
    {
        // Kiểm tra nếu người dùng là Admin
        if ($user->role_id === 1) {
            return true;
        }

        // Kiểm tra nếu người dùng là Manager và có quyền thao tác với worktime
        if ($user->role_id === 2) {
            // Bạn có thể thêm điều kiện kiểm tra nếu Manager có quyền di chuyển task
            return true;
        }

        // Staff không có quyền di chuyển task
        if ($user->role_id === 3) {
            return false;
        }

        // Mặc định trả về false nếu không có quyền
        return false;
    }

    // App/Policies/TaskPolicy.php

    public function viewWithoutWorktime(User $user): bool
    {
        // Admin có thể xem tất cả các task không có worktime_id
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem tất cả các task không có worktime_id
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xem các task không có worktime_id mà họ tạo hoặc thuộc department của họ
        if ($user->role_id === 3) {
            return true;
        }

        // Mặc định không cho phép xem
        return false;
    }


    // App/Policies/TaskPolicy.php
    public function viewTasksByWorktimeId(User $user, $worktimeId): bool
    {
        // Admin có thể xem tất cả các task theo worktime_id
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem tất cả các task theo worktime_id
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xem task theo worktime_id nếu họ đã tạo task hoặc nếu task thuộc department của họ
        if ($user->role_id === 3) {
            // Kiểm tra các điều kiện bổ sung ở đây (ví dụ: check các department user tham gia)
            return true; // Điều kiện này có thể thay đổi tuỳ vào yêu cầu thực tế
        }

        // Mặc định không cho phép xem
        return false;
    }
}
