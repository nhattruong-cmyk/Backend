<?php

namespace App\Policies;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin có quyền xem tất cả các task
        if ($user->role_id === 1) {
            return true;
        }
    
        // Manager có quyền xem tất cả các task thuộc các project mà họ quản lý
        if ($user->role_id === 2) {
            return true;
        }
    
        // Staff chỉ có thể xem task thuộc department của họ
        if ($user->role_id === 3) {
            // Duyệt qua các department của user
            foreach ($user->departments as $department) {
                // Nếu task có liên quan đến department của user
                if ($department->tasks->isNotEmpty()) {
                    return true;
                }
            }
        }
    
        return false;
    }
    
    
    
    
    
    
    
    

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task): bool
    {
        // Admin có thể xem tất cả các task
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem task nếu họ quản lý dự án liên quan đến task đó
        if ($user->role_id === 2) {
            return $task->project->manager_id === $user->id;
        }

        // Staff có thể xem task nếu task thuộc phòng ban mà họ thuộc về
        if ($user->role_id === 3) {
            return $task->departments->contains($user->departments);
        }

        // Mặc định, nếu không thỏa mãn bất kỳ điều kiện nào, không có quyền xem
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

        // Staff không có quyền tạo task
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

        // Staff chỉ có thể cập nhật trạng thái của task
        if ($user->role_id === 3 && isset($task->status)) {
            return true;
        }

        // Nếu không thoả mãn bất kỳ điều kiện nào
        return false;
    }



    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        // Admin có thể xóa bất kỳ task nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa task thuộc dự án mà họ quản lý
        if ($user->role_id === 2) {
            // Kiểm tra xem dự án của task có thuộc quản lý của manager này không
            foreach ($task->projects as $project) {
                if ($project->manager_id === $user->id) {
                    return true;
                }
            }
        }

        // Staff không có quyền xóa task
        return false;
    }


    /**
     * Determine whether the user can restore the model.
     */
    // Phân quyền cho khôi phục task
    public function restore(User $user, Task $task): bool
    {
        // Admin có thể khôi phục bất kỳ task nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể khôi phục task trong dự án mà họ quản lý
        if ($user->role_id === 2) {
            foreach ($task->projects as $project) {
                if ($project->manager_id === $user->id) {
                    return true;
                }
            }
        }

        // Staff không có quyền khôi phục task
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
}
