<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\User;
use App\Models\Task;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\TaskAssignedMail;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use Illuminate\Http\Request;
use Newsletter;
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

    public function store(Request $request)
    {
        try {
            // Lấy và xác thực dữ liệu từ request
            $validatedData = $request->validate([
                'task_id' => 'required|exists:tasks,id',
                'department_id' => 'required|exists:departments,id',
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
                'note' => 'nullable|string',
            ]);

            // Lấy thông tin task
            $task = Task::findOrFail($validatedData['task_id']);

            // Lấy danh sách user hợp lệ trong phòng ban
            $validUsersInDepartment = DB::table('department_user')
                ->where('department_id', $validatedData['department_id'])
                ->pluck('user_id')
                ->toArray();

            $invalidDepartmentUsers = [];
            $assignedUsers = [];

            foreach ($validatedData['user_ids'] as $user_id) {
                // Kiểm tra người dùng có thuộc phòng ban không
                if (!in_array($user_id, $validUsersInDepartment)) {
                    $invalidDepartmentUsers[] = $user_id;
                    continue;
                }

                // Kiểm tra người dùng đã được phân công chưa
                $existingAssignment = Assignment::where('task_id', $task->id)
                    ->where('user_id', $user_id)
                    ->where('department_id', $validatedData['department_id'])
                    ->exists();

                if ($existingAssignment) {
                    continue;
                }

                // Tạo phân công mới
                Assignment::create([
                    'task_id' => $task->id,
                    'user_id' => $user_id,
                    'department_id' => $validatedData['department_id'],
                    'note' => $validatedData['note'] ?? '',
                    'status' => 'assigned', // Hoặc trạng thái khác nếu cần
                ]);

                // Gửi email thông báo xác nhận
                $user = User::find($user_id);

                if ($user && $user->email) {
                    try {
                        Mail::to($user->email)->send(new TaskAssignedMail($task, $note ?? 'No notes available'));

                    } catch (\Exception $e) {
                        Log::error("Failed to send email to user {$user->email}: " . $e->getMessage());
                    }
                }

                // Ghi nhận người dùng đã được phân công
                $assignedUsers[] = $user_id;
            }

            // Gửi phản hồi thành công với danh sách người dùng được phân công
            return response()->json([
                'message' => 'Task assigned successfully',
                'task' => $task,
                'assigned_users' => User::whereIn('id', $assignedUsers)->get(),
                'invalid_users' => User::whereIn('id', $invalidDepartmentUsers)->get(),
            ], 201);
        } catch (\Exception $e) {
            // Trả về lỗi nếu có ngoại lệ
            return response()->json(['error' => 'Failed to assign task: ' . $e->getMessage()], 500);
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

    public function getTrashed()
    {
        try {
            // Lấy danh sách assignment đã xóa mềm
            $trashedAssignments = Assignment::onlyTrashed()->get();

            if ($trashedAssignments->isEmpty()) {
                return response()->json(['message' => 'No trashed assignments found'], 404);
            }

            return response()->json(['data' => $trashedAssignments], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve trashed assignments: ' . $e->getMessage()], 500);
        }
    }

    public function restore($id)
    {
        try {
            // Tìm assignment đã xóa mềm
            $assignment = Assignment::onlyTrashed()->findOrFail($id);

            // Khôi phục assignment
            $assignment->restore();

            return response()->json(['message' => 'Assignment restored successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore assignment: ' . $e->getMessage()], 500);
        }
    }
}
