<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DepartmentPolicy
{

    public function viewAny(User $user): bool
    {
        // Admin có thể xem bất kỳ phòng ban nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem tất cả phòng ban (vì quản lý nhiều phòng ban)
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xem các phòng ban mà họ là thành viên hoặc đã tạo (dựa trên create_by)
        if ($user->role_id === 3) {
            // Lấy giá trị 'create_by' và chuyển thành mảng
            $createBy = json_decode($user->create_by, true);

            // Nếu 'create_by' có giá trị, cho phép xem phòng ban mà họ đã tạo
            if (!empty($createBy)) {
                return true;
            }

            // Nếu 'create_by' trống, người dùng có thể xem các phòng ban mà họ là thành viên
            return true;
        }

        // Nếu không thuộc các trường hợp trên, từ chối quyền
        return false;
    }

    public function view(User $user, Department $department): bool
    {
        // Admin có thể xem bất kỳ phòng ban nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xem bất kỳ phòng ban nào
        if ($user->role_id === 2) {
            return true;
        }

        // Staff có thể xem phòng ban mà họ là thành viên
        if ($user->role_id === 3) {
            // Kiểm tra giá trị 'create_by'
            $createBy = json_decode($user->create_by, true);

            // Nếu 'create_by' có giá trị, cho phép xem phòng ban mà họ đã tạo
            if (!empty($createBy)) {
                return in_array($department->id, $createBy); // Kiểm tra xem phòng ban có ID trong mảng 'create_by'
            }

            // Nếu 'create_by' trống, cho phép xem các phòng ban mà người dùng là thành viên
            return $department->users()->where('user_id', $user->id)->exists();
        }

        // Nếu không thuộc các trường hợp trên, từ chối quyền
        return false;
    }
    public function create(User $user): bool
    {
        // Admin, Manager và Staff (role_id = 3) đều có thể tạo phòng ban
        return in_array($user->role_id, [1, 2, 3]);
    }

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

        // Staff có thể xóa mềm phòng ban nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && !is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            // Bạn có thể gọi phương thức soft delete tại đây thay vì update()
            $department->update(); // Hoặc $department->softDelete() tùy theo yêu cầu
            return true;
        }

        // Nếu không có quyền, trả về false
        return false;
    }

    public function delete(User $user, Department $department): bool
    {
        // Admin có thể xóa bất kỳ phòng ban nào
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa phòng ban nếu họ quản lý phòng ban đó
        if ($user->role_id === 2) {
            // Kiểm tra xem manager có quyền quản lý phòng ban này không
            // Giả sử bạn có mối quan hệ 'department' trên User để kiểm tra
            if ($user->departments->contains($department)) {
                return true;
            }
        }

        // Kiểm tra số lượng người dùng trong phòng ban trước khi xóa
        $usersInDepartment = $department->users;
        if ($usersInDepartment->count() == 2) {
            return response()->json([
                'error' => 'Department cannot be deleted because there are only 2 members.'
            ], 400);
        }

        // Staff có thể xóa mềm phòng ban nếu có cột create_by và có dữ liệu
        if ($user->role_id === 3 && !is_null($user->create_by)) {
            // Nếu cột create_by có giá trị, cho phép xóa mềm (soft delete)
            $department->delete(); // Xóa mềm phòng ban
            return true;
        }

        // Nếu không thỏa mãn điều kiện nào, không cho phép xóa
        return false;
    }

    public function removeUserFromDepartment(User $user, Department $department): bool
    {
        // Admin có thể xóa bất kỳ người dùng nào khỏi phòng ban
        if ($user->role_id === 1) {
            return true;
        }

        // Manager có thể xóa người dùng khỏi phòng ban nếu họ quản lý phòng ban đó
        if ($user->role_id === 2 && $department->managers->contains($user)) {
            return true;
        }

        // Staff có thể xóa người dùng khỏi phòng ban nếu họ đã tạo phòng ban (có create_by)
        if ($user->role_id === 3 && !is_null($user->create_by)) {
            return true;
        }

        // Nếu không có điều kiện nào trên, không cho phép xóa
        return false;
    }

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
            // $department->restore(); // Xóa mềm phòng ban
            return true;
        }

        // Staff không có quyền xóa phòng ban nếu không có cột create_by
        return false;
    }

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
