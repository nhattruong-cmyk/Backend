<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
    try {
        // Xác thực dữ liệu đầu vào với quy tắc unique
        $validatedData = $request->validate([
            'department_name' => [
                'required',
                'string',
                'max:255',
                'unique:departments,department_name', // Quy tắc unique
            ],
            'description' => 'nullable|string',
            'user_ids' => 'sometimes|array', // Mảng ID của người dùng
            'user_ids.*' => 'exists:users,id' // Mỗi ID phải hợp lệ trong bảng users
        ], [
            'department_name.unique' => 'Tên phòng ban đã tồn tại. Vui lòng chọn tên khác.',
        ]);

        // Tạo phòng ban mới
        $department = Department::create($validatedData);

        // Nếu có danh sách người dùng, gán họ vào phòng ban
        if ($request->has('user_ids')) {
            $department->users()->sync($request->input('user_ids'));
        }

        return response()->json([
            'message' => 'Department created successfully',
            'department' => $department->load('users')
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // Bắt lỗi xác thực và trả về thông báo lỗi
        return response()->json([
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        // Bắt lỗi chung và trả về thông báo lỗi
        return response()->json([
            'error' => 'Failed to create department: ' . $e->getMessage()
        ], 500);
    }
    }

    // Hàm cập nhật phòng ban và gán người dùng
    public function update(Request $request, $id)
{
    try {
        // Tìm phòng ban theo ID
        $department = Department::findOrFail($id);

        // Xác thực dữ liệu đầu vào với quy tắc unique, bỏ qua phòng ban hiện tại
        $validatedData = $request->validate([
            'department_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'department_name')->ignore($department->id), // Bỏ qua phòng ban hiện tại khi kiểm tra unique
            ],
            'description' => 'sometimes|nullable|string',
            'user_ids' => 'sometimes|array', // Mảng ID của người dùng
            'user_ids.*' => 'exists:users,id' // Mỗi ID phải hợp lệ trong bảng users
        ], [
            'department_name.unique' => 'Tên phòng ban đã tồn tại. Vui lòng chọn tên khác.',
        ]);

        // Cập nhật thông tin phòng ban
        $department->update($validatedData);

        // Nếu có danh sách user_ids để cập nhật
        if ($request->has('user_ids')) {
            $userIds = $request->input('user_ids');

            // Lấy danh sách người dùng đã tồn tại trong phòng ban
            $existingUsers = $department->users()->whereIn('user_id', $userIds)->get();

            // Nếu có người dùng trùng lặp, trả về lỗi với tên người dùng
            if ($existingUsers->isNotEmpty()) {
                $existingUserNames = $existingUsers->pluck('name')->toArray();
                return response()->json([
                    'error' => 'Người dùng sau đã có trong phòng ban: ' . implode(', ', $existingUserNames)
                ], 400);
            }

            // Gán người dùng vào phòng ban nếu không có trùng lặp
            $department->users()->syncWithoutDetaching($userIds); // Không loại bỏ các người dùng đã có trước
        }

        return response()->json([
            'message' => 'Phòng ban và người dùng được cập nhật thành công',
            'department' => $department->load('users')
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // Bắt lỗi xác thực và trả về thông báo lỗi
        return response()->json([
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        // Bắt lỗi chung và trả về thông báo lỗi
        return response()->json([
            'error' => 'Không thể cập nhật phòng ban: ' . $e->getMessage()
        ], 500);
    }
    }
    
    // lấy địa chỉ phòng ban và show lên màng hình
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
    
    // Xóa phòng ban
    public function destroy($id)
    {
        $department = Department::find($id);

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted successfully']);
    }
}
