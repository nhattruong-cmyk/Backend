<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;


class ProjectPolicy
{

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

    public function create(User $user): bool
    {
        // Chỉ Admin, Manager, và Staff (role_id = 3 và có create_by) mới có thể tạo project
        return ($user->role_id === 1 || $user->role_id === 2 || ($user->role_id === 3 && $user->create_by !== null));
    }

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

    public function delete(User $user, Project $project)
    {
        // Kiểm tra xem người dùng là Admin (role_id = 1) hoặc Manager (role_id = 2)
        if ($user->role_id === 1 || $user->role_id === 2) {
            return true; // Admin và Manager có quyền xóa bất kỳ dự án nào
        }
    
        // Kiểm tra đối với Staff (role_id = 3)
        if ($user->role_id === 3) {
            // Kiểm tra trạng thái của dự án (phải là 1 hoặc 4)
            if (!in_array($project->status, [1, 4])) {
                return response()->json(['message' => 'You can only delete projects with status 1 or 4.'], 403); // Trạng thái dự án không hợp lệ
            }
    
            // Kiểm tra xem dự án có phòng ban nào không
            if ($project->departments()->count() > 0) {
                return response()->json(['message' => 'You cannot delete this project because it has departments.'], 403); // Dự án có phòng ban
            }
    
            // Kiểm tra xem cột create_by có phải là mảng hay không
            if (!is_array($user->create_by)) {
                return response()->json(['message' => 'You cannot delete this project because create_by is not an array.'], 403); // create_by không phải là mảng
            }
    
            // Nếu tất cả các điều kiện đều thỏa mãn, cho phép xóa
            $project->delete(); // Xóa dự án (hoặc thực hiện soft delete nếu cần)
            return response()->json(['message' => 'Project deleted successfully.'], 200);
        }
    
        // Trả về lỗi nếu không thỏa mãn các điều kiện trên
        return response()->json(['message' => 'You do not have permission to delete this project.'], 403);
    }
    
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
