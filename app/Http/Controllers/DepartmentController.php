<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with('users')->get();
        return response()->json($departments);
    }

    public function store(StoreDepartmentRequest $request)
    {
        try {
            // Dữ liệu đã được xác thực bởi StoreDepartmentRequest
            $validatedData = $request->validated();


            // Tạo phòng ban mới
            $department = Department::create($validatedData);

            // Nếu có danh sách người dùng, gán họ vào phòng ban
            if ($request->has('user_ids')) {
                $department->users()->sync($request->input('user_ids'));
            }

            $users = $department->users;
            foreach ($users as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'message' => "Bạn đã được thêm vào phòng ban '{$department->department_name}'",
                    'read' => false
                ]);
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

    public function addUsersToDepartment(Request $request, $department_id)
    {
        try {
            // Xác thực đầu vào, chấp nhận cả `user_ids` (mảng) hoặc `user_id` (đơn lẻ)
            $validatedData = $request->validate([
                'user_ids' => 'sometimes|array', // Mảng user_ids
                'user_ids.*' => 'integer|exists:users,id', // Mỗi user_id trong mảng phải hợp lệ
                'user_id' => 'sometimes|integer|exists:users,id' // user_id đơn lẻ phải hợp lệ
            ], [
                'user_ids.*.exists' => 'Một hoặc nhiều user không tồn tại trong hệ thống.',
                'user_id.exists' => 'User không tồn tại trong hệ thống.'
            ]);
    
            // Kiểm tra xem `user_ids` hay `user_id` được cung cấp
            $userIds = [];
    
            // Nếu là `user_ids` (mảng), lấy toàn bộ giá trị trong mảng
            if (isset($validatedData['user_ids'])) {
                $userIds = $validatedData['user_ids'];
            }
    
            // Nếu là `user_id` (đơn lẻ), thêm vào mảng `userIds`
            if (isset($validatedData['user_id'])) {
                $userIds[] = $validatedData['user_id'];
            }
    
            // Kiểm tra xem có user nào bị xóa mềm hay không
            $deletedUsers = User::whereIn('id', $userIds)->whereNotNull('deleted_at')->pluck('id')->toArray();
    
            if (!empty($deletedUsers)) {
                // Nếu có user bị xóa, trả về lỗi
                return response()->json([
                    'error' => 'Không thể thêm các user sau vào phòng ban vì họ đã bị xóa: ' . implode(', ', $deletedUsers)
                ], 400);
            }
    
            // Tìm phòng ban theo ID
            $department = Department::findOrFail($department_id);
    
            // Gán user vào phòng ban, nếu user đã tồn tại thì bỏ qua (syncWithoutDetaching)
            $department->users()->syncWithoutDetaching($userIds);
    
            // Lấy danh sách user được thêm vào để tạo thông báo
            $users = User::whereIn('id', $userIds)->get();
    
            foreach ($users as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'message' => "Bạn đã được thêm vào phòng ban '{$department->department_name}'",
                    'read' => false
                ]);
            }
    
            return response()->json([
                'message' => 'Users added to department successfully.',
                'department' => $department->load('users')
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add users to department: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function update(UpdateDepartmentRequest $request, $departmentId)
    {
        try {
            // Tìm phòng ban theo ID
            $department = Department::findOrFail($departmentId);

            $validatedData = $request->validated();

            // Lưu trữ thông tin ban đầu của phòng ban để so sánh sau khi cập nhật
            $originalName = $department->department_name;
            $originalDescription = $department->description;

            // Cập nhật tên và mô tả của phòng ban nếu có
            $department->update($validatedData);

            // Kiểm tra nếu có thay đổi tên hoặc mô tả phòng ban
            $hasNameChanged = isset($validatedData['department_name']) && $originalName !== $validatedData['department_name'];
            $hasDescriptionChanged = isset($validatedData['description']) && $originalDescription !== $validatedData['description'];

            // Gửi thông báo nếu có thay đổi tên hoặc mô tả
            if ($hasNameChanged || $hasDescriptionChanged) {
                $message = 'Phòng ban của bạn đã có cập nhật mới: ';
                if ($hasNameChanged) {
                    $message .= "Tên phòng ban đã được đổi từ '{$originalName}' thành '{$department->department_name}'. ";
                }
                if ($hasDescriptionChanged) {
                    $message .= 'Mô tả phòng ban đã được thay đổi.';
                }

                // Gửi thông báo đến tất cả các user trong phòng ban
                foreach ($department->users as $user) {
                    Notification::create([
                        'user_id' => $user->id,
                        'message' => $message,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Department updated successfully.',
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
                'error' => 'Failed to update department: ' . $e->getMessage()
            ], 500);
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

    public function removeUsersFromDepartment(Request $request, $department_id)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'user_id' => 'sometimes|integer|exists:users,id', // Chấp nhận 1 ID duy nhất
            'user_ids' => 'sometimes|array', // Chấp nhận mảng các ID
            'user_ids.*' => 'integer|exists:users,id' // Kiểm tra tính hợp lệ của từng ID trong mảng
        ]);

        // Tìm phòng ban theo ID
        $department = Department::find($department_id);

        // Kiểm tra nếu phòng ban không tồn tại
        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        // Lấy danh sách user IDs từ request, ưu tiên sử dụng `user_ids` nếu có, nếu không sử dụng `user_id`
        $userIds = $request->has('user_ids') ? $request->input('user_ids') : [$request->input('user_id')];

        // Kiểm tra những user đang thuộc phòng ban hiện tại
        $existingUserIds = $department->users()
            ->whereIn('users.id', $userIds) // Sử dụng `users.id` để xác định đúng cột
            ->pluck('users.id')
            ->toArray();

        // Nếu không có user nào trong phòng ban, trả về thông báo lỗi
        if (empty($existingUserIds)) {
            return response()->json(['error' => 'No users found in the department for removal.'], 400);
        }

        try {
            // Xóa người dùng khỏi phòng ban
            $department->users()->detach($existingUserIds);

            // Lấy danh sách user ra khỏi phòng ban để tạo thông báo
            $users = User::whereIn('id', $existingUserIds)->get();

            // Tạo thông báo cho mỗi người dùng
            foreach ($users as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'message' => "Bạn đã bị xóa khỏi phòng ban '{$department->department_name}'.",
                    'read' => false,
                ]);
            }

            return response()->json(['message' => 'Users removed from department and notified successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to remove users from department: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Tìm phòng ban theo ID
            $department = Department::findOrFail($id);
    
            // Lấy tất cả user trong phòng ban
            $usersInDepartment = $department->users; // Giả sử có quan hệ 'users' trong model Department
    
            // Xóa tất cả liên kết giữa user và phòng ban trong bảng department_user
            $department->users()->detach(); // Gỡ tất cả user khỏi phòng ban
    
            // Thực hiện xóa mềm (soft delete) phòng ban
            $department->delete();
    
            return response()->json(['message' => 'Department soft deleted successfully'], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            // Bắt lỗi khóa ngoại (nếu có)
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'Department cannot be soft deleted because it is associated with users, projects, or tasks.'
                ], 400);
            }
            return response()->json(['error' => 'Failed to soft delete department: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to soft delete department: ' . $e->getMessage()], 500);
        }
    }
    
    public function restore($id)
    {
        try {
            // Tìm phòng ban đã xóa mềm
            $department = Department::onlyTrashed()->findOrFail($id);

            // Thực hiện khôi phục
            $department->restore();

            return response()->json(['message' => 'Department restored successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore department: ' . $e->getMessage()], 500);
        }
    }

    public function getTrashed()
    {
        try {
            // Lấy danh sách phòng ban đã xóa mềm
            $trashedDepartments = Department::onlyTrashed()->get();

            if ($trashedDepartments->isEmpty()) {
                return response()->json(['message' => 'No trashed departments found'], 404);
            }

            return response()->json(['data' => $trashedDepartments], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve trashed departments: ' . $e->getMessage()], 500);
        }
    }

    public function forceDelete($id)
    {
        try {
            // Tìm phòng ban đã bị xóa mềm
            $department = Department::onlyTrashed()->findOrFail($id);

            // Thực hiện xóa cứng
            $department->forceDelete();

            return response()->json(['message' => 'Department permanently deleted successfully'], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => 'Failed to permanently delete department: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to permanently delete department: ' . $e->getMessage()], 500);
        }
    }
    
}
