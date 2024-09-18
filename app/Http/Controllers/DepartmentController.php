<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    // Lấy danh sách phòng ban
    public function index()
    {
        $departments = Department::with('users')->get();
        return response()->json($departments);
    }

    // Tạo phòng ban mới
    public function store(Request $request)
    {
        $request->validate([
            'department_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $department = Department::create($request->all());

        return response()->json(['message' => 'Department created successfully', 'department' => $department], 201);
    }
    public function show($id)
    {
        // Tìm phòng ban theo id, kèm theo thông tin các người dùng liên quan
        $department = Department::with('users')->find($id);

        // Kiểm tra nếu phòng ban không tồn tại
        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        return response()->json($department);
    }

    // Thêm thành viên vào phòng ban
    public function addUserToDepartment(Request $request, $department_id)
    {
        // Kiểm tra dữ liệu đầu vào
        $request->validate([
            'user_ids' => 'required',
            'user_ids.*' => 'exists:users,id', // Kiểm tra từng user_id có tồn tại trong bảng users
        ]);

        $department = Department::find($department_id);

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        // Kiểm tra xem user_ids là mảng hay đơn
        $userIds = $request->input('user_ids');
        if (!is_array($userIds)) {
            $userIds = [$userIds]; // Nếu không phải mảng, chuyển đổi thành mảng
        }

        // Thêm người dùng vào phòng ban
        $department->users()->syncWithoutDetaching($userIds);

        return response()->json(['message' => 'Users added to department successfully']);
    }



    // Xóa thành viên khỏi phòng ban
    public function removeUserFromDepartment(Request $request, $department_id)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $department = Department::find($department_id);

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $userIds = $request->input('user_ids');

        // Xóa người dùng khỏi phòng ban
        $department->users()->detach($userIds);

        return response()->json(['message' => 'Users removed from department successfully']);
    }


    // Cập nhật phòng ban
    public function update(Request $request, $department_id)
    {
        $request->validate([
            'department_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $department = Department::find($department_id);

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $department->update($request->all());

        return response()->json(['message' => 'Department updated successfully']);
    }

    // Xóa phòng ban
    public function destroy($department_id)
    {
        $department = Department::find($department_id);

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted successfully']);
    }
}
