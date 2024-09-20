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
        $department = Department::with('users', 'tasks')->find($id);

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
        
        // thêm hàm nếu đã tồn tại trong phòng ban thì báo lỗi can't add user to department

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
        try {
            // Xác thực dữ liệu đầu vào với đôi khi (sometimes) xác thực
            $request->validate([
                'department_name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|nullable|string',
                'user_ids' => 'sometimes|array', // Nhận mảng ID của người dùng
                'user_ids.*' => 'exists:users,id' // Mỗi ID phải là hợp lệ trong bảng users
            ]);
    
            // Tìm phòng ban theo ID
            $department = Department::find($department_id);
    
            if (!$department) {
                return response()->json(['message' => 'Department not found'], 404);
            }
    
            // Cập nhật thông tin phòng ban, chỉ cập nhật những trường có mặt trong request
            $department->update($request->only(['department_name', 'description']));
    
            // Kiểm tra nếu có danh sách user_id để cập nhật
            if ($request->has('user_ids')) {
                // Gán người dùng vào phòng ban (sử dụng sync để cập nhật)
                $department->users()->sync($request->input('user_ids'));
            }
    
            return response()->json(['message' => 'Department and users updated successfully', 'department' => $department], 200);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Bắt lỗi xác thực và trả về chi tiết lỗi
            return response()->json(['error' => $e->errors()], 422);
    
        } catch (\Exception $e) {
            // Bắt lỗi chung và trả về thông báo lỗi
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
