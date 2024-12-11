<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\User;
use App\Models\Task;
use App\Models\Project;
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
    public function index(Request $request)
    {
        $this->authorize('viewAny', Assignment::class);

        $user = $request->user();

        // Kiểm tra quyền truy cập cho Admin và Manager
        if ($user->role_id === 1 || $user->role_id === 2) {
            // Admin và Manager có thể xem tất cả assignments
            $assignments = Assignment::with('user', 'department', 'task')->get();
        } elseif ($user->role_id === 3) {
            // Staff
            if ($user->create_by !== null) {
                // Nếu có create_by, hiển thị các assignments mà user là taskmaster
                $assignments = Assignment::where('taskmaster', $user->id)
                    ->with('user', 'department', 'task')
                    ->get();
            } else {
                // Nếu không có create_by, chỉ hiển thị các assignments mà user là người nhận
                $assignments = Assignment::where('user_id', $user->id)
                    ->with('user', 'department', 'task')
                    ->get();
            }
        } else {
            // Nếu không thỏa mãn điều kiện, trả về lỗi
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($assignments);
    }

    public function show($id)
    {
        $assignment = Assignment::with('user', 'task', 'department')->findOrFail($id);
    
        // Kiểm tra quyền xem Assignment
        $this->authorize('view', $assignment);  // Truyền đối tượng Assignment thay vì Assignment::class
    
        if (!$assignment) {
            return response()->json(['message' => 'Không tìm thấy phân công'], 404);
        }
    
        return response()->json($assignment);
    }

    public function getDepartmentsByTask($task_id)
    {
        try {
            // Tìm task và nạp các phòng ban liên kết
            $task = Task::with('departments')->findOrFail($task_id);

            return response()->json([
                'message' => 'Lấy danh sách phòng ban thành công',
                'departments' => $task->departments
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lấy danh sách phòng ban thất bại: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getUsersByDepartment($department_id)
    {
        try {
            // Tìm phòng ban và nạp danh sách người dùng liên kết
            $department = Department::with('users')->findOrFail($department_id);

            return response()->json([
                'message' => 'Lấy danh sách người dùng thành công',
                'users' => $department->users
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lấy danh sách người dùng thất bại: ' . $e->getMessage()
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
                'project_id' => 'required|exists:projects,id',  // Xác thực project_id
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
                'note' => 'nullable|string',
                'taskmaster' => 'nullable|exists:users,id', // Cột taskmaster vẫn còn xác thực nhưng sẽ mặc định lấy người dùng hiện tại
            ]);

            // Lấy thông tin task
            $task = Task::findOrFail($validatedData['task_id']);

            $this->authorize('create', Assignment::class); // Kiểm tra quyền xem Assignment

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

                // Thêm người dùng vào phân công với project_id và taskmaster là người dùng hiện tại
                $assignment = Assignment::create([
                    'task_id' => $task->id,
                    'user_id' => $user_id,
                    'department_id' => $validatedData['department_id'],
                    'project_id' => $validatedData['project_id'],
                    'note' => $validatedData['note'] ?? '',
                    'status' => 'assigned',
                    'taskmaster' => Auth::user()->id, // Thêm taskmaster lấy từ người dùng hiện tại
                ]);

                // Thêm người dùng vào bảng phụ task_user
                $task->users()->attach($user_id);

                // Gửi email thông báo xác nhận
                $user = User::find($user_id);

                if ($user && $user->email) {
                    try {
                        Mail::to($user->email)->send(new TaskAssignedMail($task, $validatedData['note'] ?? 'Không có ghi chú'));
                    } catch (\Exception $e) {
                        Log::error("Không thể gửi email cho người dùng {$user->email}: " . $e->getMessage());
                    }
                }

                // Ghi nhận người dùng đã được phân công
                $assignedUsers[] = $user_id;
            }

            // Gửi phản hồi thành công với danh sách người dùng được phân công
            return response()->json([
                'message' => 'Phân công nhiệm vụ thành công',
                'task' => $task,
                'assigned_users' => User::whereIn('id', $assignedUsers)->get(),
                'invalid_users' => User::whereIn('id', $invalidDepartmentUsers)->get(),
            ], 201);
        } catch (\Exception $e) {
            // Trả về lỗi nếu có ngoại lệ
            return response()->json(['error' => 'Phân công nhiệm vụ thất bại: ' . $e->getMessage()], 500);
        }
    }

    public function update(UpdateAssignmentRequest $request, $id)
    {
        try {
            // Tìm assignment theo ID
            $assignment = Assignment::findOrFail($id);

            $this->authorize('update', Assignment::class); // Kiểm tra quyền xem Assignment
            // Nếu muốn cập nhật user, department hoặc project, cần kiểm tra lại
            if ($request->has('user_id') || $request->has('department_id') || $request->has('project_id')) {
                $newUserId = $request->input('user_id', $assignment->user_id);
                $newDepartmentId = $request->input('department_id', $assignment->department_id);
                $newProjectId = $request->input('project_id', $assignment->project_id);

                // Tìm phòng ban, người dùng và dự án mới
                $department = Department::findOrFail($newDepartmentId);
                $user = User::findOrFail($newUserId);
                $project = Project::findOrFail($newProjectId);

                // Kiểm tra xem người dùng đã thuộc phòng ban chưa
                if (!$department->users->contains($user->id)) {
                    return response()->json(['error' => 'Người dùng không thuộc phòng ban đã chọn.'], 400);
                }

                // Kiểm tra xem phân công có thay đổi dự án hay không
                if ($assignment->project_id != $newProjectId) {
                    $assignment->update([
                        'project_id' => $newProjectId,
                    ]);
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

            // Cập nhật ghi chú (note) nếu có thay đổi
            if ($request->has('note') && $assignment->note != $request->note) {
                $assignment->note = $request->note;
                $assignment->save();
            }

            // Tạo thông báo nếu có thay đổi quan trọng
            Notification::create([
                'user_id' => $assignment->user_id,
                'message' => 'Phân công của bạn đã được cập nhật.',
                'read' => false,
            ]);

            return response()->json(['message' => 'Cập nhật phân công thành công', 'assignment' => $assignment], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Cập nhật phân công thất bại: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Tìm assignment theo ID
            $assignment = Assignment::findOrFail($id);
    
            // Phân quyền xóa phân công
            $this->authorize('delete', $assignment); // Truyền assignment vào để phân quyền
    
            // Thực hiện xóa mềm
            $assignment->delete();
    
            return response()->json(['message' => 'Xóa phân công mềm thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Xóa phân công thất bại: ' . $e->getMessage()], 500);
        }
    }
    
    

    public function getTrashed()
    {
        try {
            // Lấy danh sách assignment đã xóa mềm
            $trashedAssignments = Assignment::onlyTrashed()->get();

            if ($trashedAssignments->isEmpty()) {
                return response()->json(['message' => 'Không có phân công đã xóa'], 404);
            }

            return response()->json(['data' => $trashedAssignments], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Lấy phân công đã xóa thất bại: ' . $e->getMessage()], 500);
        }
    }

    public function restore($id)
    {
        try {
            // Tìm task đã bị xóa mềm
            $assignment = Assignment::onlyTrashed()->findOrFail($id);

            // Kiểm tra phân quyền khôi phục
            $this->authorize('restore', $assignment);

            // Khôi phục task
            $assignment->restore();

            return response()->json(['message' => 'assignment restored successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore assignment: ' . $e->getMessage()], 500);
        }
    }

    public function forceDelete($id)
    {
        try {
            // Tìm assignment theo ID, bao gồm cả bản ghi đã xóa mềm
            $assignment = Assignment::withTrashed()->find($id);
    
            if (!$assignment) {
                return response()->json(['error' => 'Không tìm thấy phân công với ID ' . $id], 404);
            }
    
            // Kiểm tra quyền xóa
            $this->authorize('delete', $assignment);
    
            // Xóa cứng
            $assignment->forceDelete();
    
            return response()->json(['message' => 'Xóa phân công thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Xóa phân công thất bại: ' . $e->getMessage()], 500);
        }
    }
    
}
