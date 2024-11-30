<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
    
        // Staff chỉ có thể xem project mà họ thuộc về
        if ($user->role_id === 3) {
            // Kiểm tra xem user có thuộc project nào không
            return $user->departments()->exists();
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
        if ($user->role_id === 2 && $project->manager_id === $user->id) {
            return true;
        }
    
        // Staff có thể xem project mà họ là thành viên
        if ($user->role_id === 3 && $project->staff()->where('user_id', $user->id)->exists()) {
            return true;
        }
    
        // Nếu không, từ chối quyền
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Chỉ Admin và Manager mới có thể tạo project
        return $user->role_id === 1 || $user->role_id === 2;
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
        if ($user->role_id === 2 && $project->manager_id === $user->id) {
            return true;
        }
    
        // Staff không có quyền cập nhật project
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        // Admin có thể xóa bất kỳ project nào
        if ($user->role_id === 1) {
            return true;
        }
    
        // Manager có thể xóa project nếu họ quản lý project đó
        if ($user->role_id === 2 && $project->manager_id === $user->id) {
            return true;
        }
    
        // Staff không có quyền xóa project
        return false;
    }
    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        // Admin có thể khôi phục project
        if ($user->role_id === 1) {
            return true;
        }
    
        // Manager có thể khôi phục project nếu họ là người quản lý
        if ($user->role_id === 2 && $project->manager_id === $user->id) {
            return true;
        }
    
        // Staff không có quyền khôi phục project
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        // Chỉ Admin (role_id = 1) và Manager (role_id = 2) có quyền xóa cứng project
        return in_array($user->role_id, [1, 2]);
    }
}
