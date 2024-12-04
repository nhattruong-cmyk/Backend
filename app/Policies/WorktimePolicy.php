<?php

namespace App\Policies;

use Illuminate\Support\Facades\Log;
use App\Models\Worktimes;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class WorktimePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin có thể xem tất cả Worktime
        if ($user->role_id === 1) {
            return true;
        }
    
        // Manager có thể xem tất cả Worktime
        if ($user->role_id === 2) {
            return true;
        }
    
        // Staff với create_by không rỗng có thể xem Worktime thuộc về họ hoặc phòng ban họ quản lý
        if ($user->role_id === 3 && !is_null($user->create_by)) {
            // Kiểm tra xem user có thuộc phòng ban nào không
            if ($user->departments()->exists()) {
                return true;
            }
    
            // Kiểm tra xem user có tạo ra Worktime nào không
            return DB::table('worktimes')->where('user_id', $user->id)->exists();
        }
    
        // Staff với create_by rỗng chỉ có thể xem Worktime của project mà họ thuộc về
        if ($user->role_id === 3 && is_null($user->create_by)) {
            // Kiểm tra xem user có thuộc project nào không và xem các worktimes có project_id trùng với user.id
            return DB::table('worktimes')->where('project_id', $user->id)->exists();
        }
    
        // Mặc định không cho phép xem worktimes
        return false;
    }
    

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Worktimes $worktime): bool
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
            return DB::table('Worktimes')->where('user_id', $user->id)->exists();
        }

        // Nếu không, từ chối quyền
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admin có quyền tạo Worktime cho bất kỳ dự án nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có quyền tạo Worktime cho dự án mà họ quản lý
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có quyền tạo Worktime mới (không cần kiểm tra dự án hoặc department)
        if ($user->role_id === 3) {
            return true;
        }

        // Mặc định, nếu không thỏa mãn bất kỳ điều kiện nào, không có quyền tạo Worktime
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Worktimes $Worktime)
    {
        // Admin có thể cập nhật tất cả các Worktime
        if ($user->role_id === 1) {
            return true;
        }

        // Manager chỉ có thể cập nhật Worktime thuộc dự án mà họ quản lý
        if ($user->role_id === 2 && $Worktime->projects->contains('manager_id', $user->id)) {
            return true;
        }

        // Staff chỉ có thể cập nhật Worktime nếu Worktime đã được phân công cho họ
        if ($user->role_id === 3) {
            return true;
        }

        // Nếu không thỏa mãn bất kỳ điều kiện nào
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Worktimes $Worktime): bool
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
            $Worktime->delete(); // Xóa mềm phòng ban
            return true;
        }
        // Staff có thể xóa mềm phòng ban nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            $Worktime->delete(); // Xóa mềm phòng ban
            return true;
        }

        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }


    /**
     * Determine whether the user can restore the model.
     */
    // Phân quyền cho khôi phục Worktime

    public function restore(User $user, Worktimes $Worktime): bool
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
            $Worktime->restore(); // Xóa mềm phòng ban
            return true;
        }

        // Staff có thể xóa mềm phòng ban nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            $Worktime->restore(); // Xóa mềm phòng ban
            return true;
        }

        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }

    // Phân quyền cho xóa cứng Worktime
    public function forceDelete(User $user, Worktimes $Worktime): bool
    {
        // Admin có thể xóa vĩnh viễn bất kỳ Worktime nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa vĩnh viễn Worktime trong dự án mà họ quản lý
        if ($user->role_id === 2) {
            foreach ($Worktime->projects as $project) {
                if ($project->manager_id === $user->id) {
                    return true;
                }
            }
        }

        // Staff không có quyền xóa vĩnh viễn Worktime
        return false;
    }

    // WorktimePolicy.php

    public function moveWorktimesToAnotherWorktime(User $user): bool
    {
        // Kiểm tra nếu người dùng là Admin
        if ($user->role_id === 1) {
            return true;
        }

        // Kiểm tra nếu người dùng là Manager và có quyền thao tác với worktime
        if ($user->role_id === 2) {
            // Bạn có thể thêm điều kiện kiểm tra nếu Manager có quyền di chuyển Worktime
            return true;
        }

        // Staff không có quyền di chuyển Worktime
        if ($user->role_id === 3) {
            return false;
        }

        // Mặc định trả về false nếu không có quyền
        return false;
    }
}
