<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DepartmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin có thể xem tất cả phòng ban
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem tất cả phòng ban
        if ($user->role_id === 2) {
            return true;
        }

        // Staff chỉ có thể xem phòng ban mà họ thuộc về
        if ($user->role_id === 3) {
            // Kiểm tra xem user có thuộc phòng ban nào không
            return $user->departments()->exists() ?: true;
        }


        // Mặc định không cho phép xem phòng ban
        return false;
    }


    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Department $department): bool
    {
        // Admin có thể xem bất kỳ phòng ban nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem phòng ban mà họ quản lý
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xem phòng ban mà họ là thành viên
        if ($user->role_id === 3 && $department->staff()->where('user_id', $user->id)->exists()) {
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
        // Admin, Manager và Staff (role_id = 3) đều có thể tạo phòng ban
        return in_array($user->role_id, [1, 2, 3]);
    }



    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Department $department): bool
    {
        // Admin có thể cập nhật tất cả các phòng ban
        if ($user->role_id === 1) {
            return true;
        }

        // Manager chỉ có thể cập nhật phòng ban mà họ quản lý
        if ($user->role_id === 2) {
            return true;
        }

               // Kiểm tra xem người dùng có vai trò Staff hay không
               if ($user->role_id === 3) {
                return true;  // Cho phép Staff mời tất cả người dùng từ bảng users
            }

        // Nếu không có quyền, trả về false
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Department $department): bool
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
            $department->delete(); // Xóa mềm phòng ban
            return true;
        }
    
        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }
    


    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Department $department): bool
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
            $department->restore(); // Xóa mềm phòng ban
            return true;
        }
    
        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }


    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Department $department): bool
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
            $department->delete(); // Xóa mềm phòng ban
            return true;
        }
    
        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }
}
