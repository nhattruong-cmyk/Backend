<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;


class AssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        // Nếu người dùng là Admin (role_id = 1) hoặc Manager (role_id = 2), họ có thể xem tất cả assignments
        if ($user->role_id === 1 || $user->role_id === 2) {
            return true; // Quản trị viên và quản lý có thể xem tất cả assignments
        }
        // Nếu là Staff, chỉ lấy các Assignment của Staff đó
        if ($user->role_id === 3 && $user->create_by !== null) {
            return true;
        }
        // Kiểm tra nếu người dùng là Staff (role_id = 3)
        if ($user->role_id === 3) {

            // Kiểm tra nếu cột create_by có dữ liệu (nghĩa là người dùng đã tạo)
            if (!empty($user->create_by)) {

                // Truy vấn tất cả các assignments mà người dùng đã tạo, dựa vào cột taskmaster
                $assignments = DB::table('assignments')
                    ->where('taskmaster', $user->id)  // Lọc theo taskmaster để tìm các assignment do người dùng tạo
                    ->get();

                // Kiểm tra nếu có assignment, trả về kết quả
                if ($assignments->isNotEmpty()) {
                    return response()->json($assignments);
                }

                return response()->json(['message' => 'No assignments found'], 404);
            }

            // Nếu create_by không có dữ liệu, chỉ trả về các assignment mà user tham gia
            $assignments = DB::table('assignments')
                ->where('user_id', $user->id)  // Lọc theo user_id để lấy các assignments của người dùng
                ->get();

            return response()->json($assignments);
        }

        // Trả về lỗi nếu không phải là Staff
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assignment $assignment): bool
    {
        // Admin có thể xem tất cả phân công
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem tất cả phân công
        if ($user->role_id === 2) {
            return true;
        }

        // Manager có thể xem tất cả phân công
        if ($user->role_id === 3) {
            return true;
        }

        // // Staff có thể xem phân công nếu họ thuộc phòng ban của phân công đó
        // if ($user->role_id === 3) {
        //     // Kiểm tra nếu người dùng thuộc phòng ban của phân công
        //     $department = $assignment->department; // Lấy phòng ban của phân công

        //     if ($department && $department->users->contains($user->id)) {
        //         return true; // Nếu phòng ban chứa người dùng, cho phép xem
        //     }

        //     // Hoặc kiểm tra nếu người dùng đã tạo ra phân công này (nếu có trường create_by hoặc tương tự)
        //     if ($assignment->user_id === $user->id) {
        //         return true; // Nếu phân công được tạo bởi người dùng này
        //     }
        // }

        // Mặc định không cho phép xem phân công nếu không thỏa mãn các điều kiện trên
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Chỉ Admin, Manager, và Staff (role_id = 3 và có create_by) mới có thể tạo project
        return ($user->role_id === 1 || $user->role_id === 2 || ($user->role_id === 3 && $user->create_by !== null) || ($user->role_id === 3 && $user->create_by == null));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        // Chỉ Admin, Manager, và Staff (role_id = 3 và có create_by) mới có thể tạo project
        return ($user->role_id === 1 || $user->role_id === 2 || ($user->role_id === 3 && $user->create_by !== null) || ($user->role_id === 3 && $user->create_by == null));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assignment $assignment): bool
    {
        // Admin có thể xóa bất kỳ phân công nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa phân công
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xóa phân công nếu họ tạo ra phân công này (có create_by)
        if ($user->role_id === 3 && $user->create_by !== null) {
            // Kiểm tra xem người dùng có tạo ra phân công này không
            return $assignment->create_by === $user->id;
        }

        // Staff có thể xóa phân công nếu create_by không có dữ liệu
        if ($user->role_id === 3 && is_null($user->create_by)) {
            return true;
        }

        // Nếu không thuộc bất kỳ điều kiện nào thì không có quyền xóa
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assignment $assignment): bool
    {
        // Admin có thể xóa bất kỳ phân công nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa phân công nếu họ quản lý phân công đó
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xóa mềm phân công nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && !is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            $assignment->restore(); // Xóa mềm phân công
            return true;
        }

        // Staff có thể xóa mềm phân công nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            $assignment->restore(); // Xóa mềm phân công
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assignment $assignment): bool
    {
        // Admin có thể xóa bất kỳ phân công nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa phân công nếu họ quản lý phân công đó
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xóa mềm phân công nếu có cột create_by (dù có dữ liệu hay không)
        if ($user->role_id === 3) {
            // Xóa mềm (soft delete) nếu thỏa mãn điều kiện
            $assignment->delete();
            return true;
        }


        // Staff không có quyền xóa phân công nếu không có cột create_by
        return false;
    }
}
