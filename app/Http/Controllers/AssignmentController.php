<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\User;
use App\Models\Task;
use App\Models\Notification;

use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index()
    {
        $assignments = Assignment::with('user', 'department', 'task')->get();
        return response()->json($assignments);
    }

    public function show($id)
    {
        $assignment = Assignment::with('user', 'task', 'department')->findOrFail($id);
        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }
        return response()->json($assignment);
    }

    public function getDepartmentsByTask($task_id)
    {
        try {
            // Tìm task và nạp các phòng ban liên kết
            $task = Task::with('departments')->findOrFail($task_id);

            return response()->json([
                'message' => 'Departments retrieved successfully',
                'departments' => $task->departments
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve departments: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getUsersByDepartment($department_id)
    {
        try {
            // Tìm phòng ban và nạp danh sách người dùng liên kết
            $department = Department::with('users')->findOrFail($department_id);

            return response()->json([
                'message' => 'Users retrieved successfully',
                'users' => $department->users
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve users: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreAssignmentRequest $request)
    {
        try {
            // Lấy dữ liệu đã xác thực từ StoreAssignmentRequest
            $validatedData = $request->validated();

            // Kiểm tra department có thuộc task không
            $task = Task::with('departments')->findOrFail($validatedData['task_id']);
            if (!$task->departments->contains($validatedData['department_id'])) {
                return response()->json(['error' => 'The department is not part of the task'], 400);
            }

            // Lấy danh sách người dùng thuộc phòng ban cụ thể
            $validUsersInDepartment = DB::table('department_user')
                ->where('department_id', $validatedData['department_id'])
                ->pluck('user_id')
                ->toArray();

            // Lưu danh sách user không thuộc phòng ban và user đã được gán nhiệm vụ
            $invalidDepartmentUsers = [];
            $duplicateUsers = [];

            // Lặp qua danh sách user_ids và kiểm tra trùng lặp và người dùng không hợp lệ
            foreach ($validatedData['user_ids'] as $user_id) {
                if (!in_array($user_id, $validUsersInDepartment)) {
                    // Người dùng không thuộc phòng ban
                    $invalidDepartmentUsers[] = $user_id;
                    continue;
                }

                // Kiểm tra trùng lặp assignment
                $existingAssignment = Assignment::where('task_id', $validatedData['task_id'])
                    ->where('user_id', $user_id)
                    ->where('department_id', $validatedData['department_id'])
                    ->first();

                if ($existingAssignment) {
                    // Người dùng đã được gán nhiệm vụ này
                    $duplicateUsers[] = $user_id;
                } else {
                    // Tạo assignment mới nếu chưa tồn tại và hợp lệ
                    Assignment::create([
                        'task_id' => $validatedData['task_id'],
                        'user_id' => $user_id,
                        'department_id' => $validatedData['department_id'],
                    ]);

                    // Cập nhật bảng `task_user`
                    $task->users()->attach($user_id);

                    // Tạo thông báo cho user
                    Notification::create([
                        'user_id' => $user_id,
                        'message' => 'You have been assigned a new task: ' . $task->task_name,
                    ]);
                }
            }

            // Trả về thông báo lỗi nếu có user không hợp lệ hoặc trùng lặp
            if (!empty($invalidDepartmentUsers) || !empty($duplicateUsers)) {
                $errorMessages = [];

                // Thông báo lỗi cho các user không thuộc phòng ban
                if (!empty($invalidDepartmentUsers)) {
                    $invalidUserNames = User::whereIn('id', $invalidDepartmentUsers)->pluck('name')->toArray();
                    $errorMessages[] = 'The following users are not part of the department: ' . implode(', ', $invalidUserNames);
                }

                // Thông báo lỗi cho các user đã được gán nhiệm vụ
                if (!empty($duplicateUsers)) {
                    $duplicateUserNames = User::whereIn('id', $duplicateUsers)->pluck('name')->toArray();
                    $errorMessages[] = 'The following users are already assigned to this task: ' . implode(', ', $duplicateUserNames);
                }

                return response()->json(['error' => implode(' | ', $errorMessages)], 400);
            }

            // Trả về thông báo thành công
            return response()->json(['message' => 'Users assigned to task successfully'], 201);
        } catch (\Exception $e) {
            // Xử lý lỗi ngoại lệ
            return response()->json(['error' => 'Failed to assign users to task: ' . $e->getMessage()], 500);
        }
    }




    public function update(UpdateAssignmentRequest $request, $id)
    {
        try {
            // Tìm assignment theo ID
            $assignment = Assignment::findOrFail($id);

            // Nếu muốn cập nhật user hoặc department, cần kiểm tra lại
            if ($request->has('user_id') || $request->has('department_id')) {
                $newUserId = $request->input('user_id', $assignment->user_id);
                $newDepartmentId = $request->input('department_id', $assignment->department_id);

                // Tìm phòng ban và người dùng mới
                $department = Department::findOrFail($newDepartmentId);
                $user = User::findOrFail($newUserId);

                // Kiểm tra xem người dùng đã thuộc phòng ban chưa
                if (!$department->users->contains($user->id)) {
                    return response()->json(['error' => 'User does not belong to the specified department.'], 400);
                }

                // Cập nhật user và department nếu có sự thay đổi
                if ($assignment->user_id != $newUserId || $assignment->department_id != $newDepartmentId) {
                    $assignment->update([
                        'user_id' => $newUserId,
                        'department_id' => $newDepartmentId,
                    ]);

                    // Đồng bộ bảng `task_user` khi thay đổi user hoặc department
                    $task = $assignment->task;
                    $task->users()->syncWithoutDetaching([$newUserId]);
                }
            }

            // Cập nhật trạng thái nếu có thay đổi
            if ($request->has('status') && $assignment->status != $request->status) {
                $assignment->status = $request->status;
                $assignment->save();
            }

            // Tạo thông báo nếu có thay đổi quan trọng
            Notification::create([
                'user_id' => $assignment->user_id,
                'message' => 'Your assignment has been updated.',
                'read' => false,
            ]);

            return response()->json(['message' => 'Assignment updated successfully', 'assignment' => $assignment], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update assignment: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Tìm assignment theo ID
            $assignment = Assignment::findOrFail($id);

            // Thực hiện xóa mềm
            $assignment->delete();

            return response()->json(['message' => 'Assignment soft deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete assignment: ' . $e->getMessage()], 500);
        }
    }
}
