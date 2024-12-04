<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;


class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin có thể xem tất cả project
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem tất cả project
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
            return DB::table('projects')->where('user_id', $user->id)->exists();
        }

        // Mặc định không cho phép xem project
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
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
            return DB::table('projects')->where('user_id', $user->id)->exists();
        }

        // Nếu không, từ chối quyền
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Chỉ Admin, Manager, và Staff (role_id = 3 và có create_by) mới có thể tạo project
        return ($user->role_id === 1 || $user->role_id === 2 || ($user->role_id === 3 && $user->create_by !== null));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        // Admin có thể cập nhật bất kỳ project nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể cập nhật project mà họ quản lý
        if ($user->role_id === 2) {
            return true;
        }

        // staff create_by có thể cập nhật tất cả các phòng ban
        if ($user->role_id === 3) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
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
            $project->delete(); // Xóa mềm phòng ban
            return true;
        }

        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }
    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
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
            $project->restore(); // Xóa mềm phòng ban
            return true;
        }

        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
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
            $project->delete(); // Xóa mềm phòng ban
            return true;
        }

        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }

    public function addDepartmentToProject(User $user, Project $project): bool
    {
        // Admin hoặc Manager có thể thêm department vào bất kỳ project nào
        if ($user->role_id === 1 || $user->role_id === 2) {
            return true;
        }

        // Staff (role_id = 3) chỉ có thể thêm department vào project mà họ đã tạo
        if ($user->role_id === 3 && $project->user_id === $user->id) {
            return true;
        }

        // Nếu không đủ quyền
        return false;
    }
}
