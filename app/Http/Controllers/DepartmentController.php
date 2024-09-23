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

    public function store(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'department_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_ids' => 'sometimes|array', // Mảng ID của người dùng
            'user_ids.*' => 'exists:users,id' // Mỗi ID phải hợp lệ trong bảng users
        ]);
    
        try {
            // Tạo phòng ban mới
            $department = Department::create($request->only(['department_name', 'description']));
    
            // Nếu có danh sách người dùng
            if ($request->has('user_ids')) {
                $userIds = $request->input('user_ids');
    
                // Lấy danh sách user đã có trong phòng ban này
                $existingUsers = $department->users()->whereIn('user_id', $userIds)->get();
    
                // Nếu có người dùng trùng lặp, in ra lỗi với tên của người dùng
                if ($existingUsers->isNotEmpty()) {
                    $existingUserNames = $existingUsers->pluck('name')->toArray();
                    return response()->json([
                        'error' => 'The following users are already in the department: ' . implode(', ', $existingUserNames)
                    ], 400);
                }
    
                // Gán người dùng vào phòng ban nếu không có lỗi
                $department->users()->sync($userIds);
            }
    
            return response()->json(['message' => 'Department created successfully', 'department' => $department], 201);
    
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create department: ' . $e->getMessage()], 500);
        }
    }
    
    public function show($id)
    {
        // Tìm phòng ban theo id, kèm theo thông tin các người dùng liên quan
        $department = Department::with('users', 'tasks')->find($id);

        // Kiểm tra nếu phòng ban không tồn tại
        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        return response()->json($department);
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
        // Xác thực dữ liệu đầu vào với đôi khi (sometimes) xác thực
        $request->validate([
            'department_name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'user_ids' => 'sometimes|array', // Nhận mảng ID của người dùng
            'user_ids.*' => 'exists:users,id' // Mỗi ID phải hợp lệ trong bảng users
        ]);
    
        try {
            // Tìm phòng ban theo ID
            $department = Department::find($department_id);
    
            if (!$department) {
                return response()->json(['message' => 'Department not found'], 404);
            }
    
            // Cập nhật thông tin phòng ban
            $department->update($request->only(['department_name', 'description']));
    
            // Nếu có danh sách user_ids để cập nhật
            if ($request->has('user_ids')) {
                $userIds = $request->input('user_ids');
    
                // Lấy danh sách người dùng đã tồn tại trong phòng ban
                $existingUsers = $department->users()->whereIn('user_id', $userIds)->get();
    
                // Nếu có người dùng trùng lặp, trả về lỗi với tên người dùng
                if ($existingUsers->isNotEmpty()) {
                    $existingUserNames = $existingUsers->pluck('name')->toArray();
                    return response()->json([
                        'error' => 'The following users are already in the department: ' . implode(', ', $existingUserNames)
                    ], 400);
                }
    
                // Gán người dùng vào phòng ban nếu không có trùng lặp
                $department->users()->syncWithoutDetaching($userIds); // Không loại bỏ các người dùng đã có trước
            }
    
            return response()->json(['message' => 'Department and users updated successfully', 'department' => $department], 200);
    
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update department: ' . $e->getMessage()], 500);
        }
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
