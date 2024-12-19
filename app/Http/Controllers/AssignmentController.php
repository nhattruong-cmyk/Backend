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
use Exception;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {

        $user = auth()->user();

        // Kiểm tra quyền truy cập cho Admin và Manager
        if ($user->role_id === 1 || $user->role_id === 2) {
            // Admin và Manager có thể xem tất cả assignments
            $assignments = Assignment::with('user', 'department', 'task')->get();
        } elseif ($user->role_id === 3) {
            // Staff
            if (!is_null($user->create_by)) {
                // Nếu có create_by, Staff có quyền xem tất cả assignments mà họ là taskmaster
                $assignments = Assignment::with('user', 'department', 'task')
                    ->where('taskmaster', $user->id) // Nhiệm vụ mà user là taskmaster
                    ->orWhere('user_id', $user->id)  // Nhiệm vụ mà user được giao
                    ->get();
            } else {
                // Nếu không có create_by, lấy các assignments mà user đã tạo hoặc được phân công cho họ
                $assignments = Assignment::where('user_id', $user->id) // Các phân công do user nhận
                    ->orWhere('taskmaster', $user->id) // Các phân công mà user đã tạo
                    ->with('user', 'department', 'task')
                    ->get();
            }
        } else {
            // Nếu không thỏa mãn điều kiện, trả về lỗi Unauthorized
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
            $department = Department::with(['users' => function ($query) {
                $query->whereHas('confirmationRequests', function ($subQuery) {
                    $subQuery->where('confirmation_status', 'confirmed');
                });
            }])->findOrFail($department_id);

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
                    ->first();

                if ($existingAssignment) {
                    // Nếu nhiệm vụ đã tồn tại, đánh dấu bản ghi cũ là duplicate và tạo nhiệm vụ mới
                    $existingAssignment->is_duplicate = true;
                    $existingAssignment->save(); // Lưu lại bản ghi cũ

                    // Tạo nhiệm vụ mới
                    $assignment = Assignment::create([
                        'task_id' => $task->id,
                        'department_id' => $validatedData['department_id'],
                        'project_id' => $validatedData['project_id'],
                        'user_id' => $user_id,
                        'note' => $validatedData['note'] ?? 'No note',
                        'status' => 'assigned',
                        'taskmaster' => $validatedData['taskmaster'] ?? auth()->id(),
                    ]);
                } else {
                    // Tạo nhiệm vụ mới nếu chưa có
                    $assignment = Assignment::create([
                        'task_id' => $task->id,
                        'department_id' => $validatedData['department_id'],
                        'project_id' => $validatedData['project_id'],
                        'user_id' => $user_id,
                        'note' => $validatedData['note'] ?? 'No note',
                        'status' => 'assigned',
                        'taskmaster' => $validatedData['taskmaster'] ?? auth()->id(),
                    ]);
                }
                // Gửi email thông báo xác nhận
                $user = User::find($user_id);

                if ($user && $user->email) {
                    try {
                        Mail::to($user->email)->send(new TaskAssignedMail($task, $validatedData['note'] ?? 'Không có ghi chú'));
                    } catch (\Exception $e) {
                        Log::error("Không thể gửi email cho người dùng {$user->email}: " . $e->getMessage());
                    }
                }

                // Lưu người dùng đã được phân công
                $assignedUsers[] = $user_id;
            }


            // Trả về phản hồi
            return response()->json([
                'message' => 'Assignments created successfully.',
                'assigned_users' => $assignedUsers,
            ], 200);
        } catch (\Exception $e) {
            // Bắt lỗi và trả về thông báo lỗi
            return response()->json([
                'error' => 'Error while creating assignment: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateAssignmentRequest $request, $id)
    {
        try {
            // Tìm assignment theo ID
            $assignment = Assignment::findOrFail($id);

            // Kiểm tra quyền cập nhật Assignment, gọi policy
            $this->authorize('update', $assignment); // Kiểm tra quyền cập nhật Assignment

            // Nếu muốn cập nhật user, department hoặc project, cần kiểm tra lại
            if ($request->has('user_id') || $request->has('department_id') || $request->has('project_id')) {
                $newUserId = $request->input('user_id', $assignment->user_id);
                $newDepartmentId = $request->input('department_id', $assignment->department_id);
                $newProjectId = $request->input('project_id', $assignment->project_id);

                // Kiểm tra sự tồn tại của phòng ban, người dùng và dự án
                $department = Department::findOrFail($newDepartmentId);
                $user = User::findOrFail($newUserId);
                $project = Project::findOrFail($newProjectId);

                // Kiểm tra xem người dùng đã thuộc phòng ban chưa
                if (!$department->users->contains($user->id)) {
                    return response()->json(['error' => 'Người dùng không thuộc phòng ban đã chọn.'], 400);
                }

                // Cập nhật project nếu có sự thay đổi
                if ($assignment->project_id != $newProjectId) {
                    $assignment->update(['project_id' => $newProjectId]);
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

            //            // Tạo thông báo nếu có thay đổi quan trọng
            //            Notification::create([
            //                'user_id' => $assignment->user_id,
            //                'message' => 'Phân công của bạn đã được cập nhật.',
            //                'read' => false,
            //            ]);

            return response()->json(['message' => 'Cập nhật phân công thành công', 'assignment' => $assignment], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Cập nhật phân công thất bại: ' . $e->getMessage()], 500);
        }
    }
    // public function update(UpdateAssignmentRequest $request, $id)
    // {
    //     try {
    //         // Tìm assignment theo ID
    //         $assignment = Assignment::findOrFail($id);

    //         $this->authorize('update', Assignment::class); // Kiểm tra quyền xem Assignment
    //         // Nếu muốn cập nhật user, department hoặc project, cần kiểm tra lại
    //         if ($request->has('user_id') || $request->has('department_id') || $request->has('project_id')) {
    //             $newUserId = $request->input('user_id', $assignment->user_id);
    //             $newDepartmentId = $request->input('department_id', $assignment->department_id);
    //             $newProjectId = $request->input('project_id', $assignment->project_id);

    //             // Tìm phòng ban, người dùng và dự án mới
    //             $department = Department::findOrFail($newDepartmentId);
    //             $user = User::findOrFail($newUserId);
    //             $project = Project::findOrFail($newProjectId);

    //             // Kiểm tra xem người dùng đã thuộc phòng ban chưa
    //             if (!$department->users->contains($user->id)) {
    //                 return response()->json(['error' => 'Người dùng không thuộc phòng ban đã chọn.'], 400);
    //             }

    //             // Kiểm tra xem phân công có thay đổi dự án hay không
    //             if ($assignment->project_id != $newProjectId) {
    //                 $assignment->update([
    //                     'project_id' => $newProjectId,
    //                 ]);
    //             }

    //             // Cập nhật user và department nếu có sự thay đổi
    //             if ($assignment->user_id != $newUserId || $assignment->department_id != $newDepartmentId) {
    //                 $assignment->update([
    //                     'user_id' => $newUserId,
    //                     'department_id' => $newDepartmentId,
    //                 ]);

    //                 // Đồng bộ bảng `task_user` khi thay đổi user hoặc department
    //                 $task = $assignment->task;
    //                 $task->users()->syncWithoutDetaching([$newUserId]);
    //             }
    //         }

    //         // Cập nhật trạng thái nếu có thay đổi
    //         if ($request->has('status') && $assignment->status != $request->status) {
    //             $assignment->status = $request->status;
    //             $assignment->save();
    //         }

    //         // Cập nhật ghi chú (note) nếu có thay đổi
    //         if ($request->has('note') && $assignment->note != $request->note) {
    //             $assignment->note = $request->note;
    //             $assignment->save();
    //         }

    //         // Tạo thông báo nếu có thay đổi quan trọng
    //         Notification::create([
    //             'user_id' => $assignment->user_id,
    //             'message' => 'Phân công của bạn đã được cập nhật.',
    //             'read' => false,
    //         ]);

    //         return response()->json(['message' => 'Cập nhật phân công thành công', 'assignment' => $assignment], 200);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Cập nhật phân công thất bại: ' . $e->getMessage()], 500);
    //     }
    // }

    public function destroy($id)
    {
        try {
            // Lấy thông tin người dùng hiện tại
            $user = auth()->user();

            // Tìm assignment theo ID
            $assignment = Assignment::findOrFail($id);

            // Kiểm tra trạng thái phân công
            $status = $assignment->status;

            // Kiểm tra quyền xóa nhiệm vụ dựa trên vai trò của người dùng và trạng thái phân công
            if ($user->role_id === 1 || $user->role_id === 2) {
                // Admin và Manager có thể xóa phân công khi trạng thái không phải 1 hoặc 4
                if (in_array($status, ['done'])) {
                    return response()->json(['error' => 'Bạn không thể xóa phân công có trạng thái này.'], 400);
                }
            } elseif ($user->role_id === 3) {
                if (!is_null($user->create_by)) {
                    // User có role_id = 3 và create_by không rỗng, chỉ có thể xóa phân công khi trạng thái là 1 hoặc 4
                    if (in_array($status, ['done'])) {
                        return response()->json(['error' => 'Bạn không thể xóa phân công có trạng thái này.'], 400);
                    }
                } else {
// Họ có thể xóa phân công nếu họ là người được phân hoặc là người tạo
if ($assignment->user_id === $user->id || $assignment->taskmaster === $user->id) {
    $assignment->delete();
    return response()->json(['message' => 'Phân công đã được xóa thành công.'], 200);
} else {
    return response()->json(['error' => 'Bạn không thể xóa phân công này.'], 403);
}

                    if (in_array($status, ['done'])) {
                        return response()->json(['error' => 'Bạn không thể xóa phân công có trạng thái này.'], 400);
                    }
                }
            } else {
                return response()->json(['error' => 'Bạn không có quyền xóa phân công này.'], 403);
            }

            // Thực hiện xóa mềm (soft delete)
            $assignment->delete();

            return response()->json(['message' => 'Xóa phân công mềm thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Xóa phân công thất bại: ' . $e->getMessage()], 500);
        }
    }

    public function getTrashed()
    {
        try {
            $user = auth()->user();
    
            // Admin hoặc Manager: Xem tất cả các phân công đã xóa
            if ($user->role_id === 1 || $user->role_id === 2) {
                $trashedAssignments = Assignment::onlyTrashed()->get();
            } elseif ($user->role_id === 3) {
                if (!is_null($user->create_by)) {
                    // Staff với create_by không rỗng: Xem các phân công đã xóa mà họ tạo
    
                    // Lấy các phân công đã xóa do họ là taskmaster
                    $trashedAssignments = Assignment::onlyTrashed()
                        ->where('user_id', $user->id)
                        ->get();
                } else {
                    // Staff với create_by rỗng: Xem các phân công đã bị xóa thuộc về user đó
                    $trashedAssignments = Assignment::onlyTrashed()
                        ->where('user_id', $user->id)
                        ->get();
                }
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
    
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
            // $this->authorize('delete', $assignment);

            // Xóa cứng
            $assignment->forceDelete();

            return response()->json(['message' => 'Xóa phân công thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Xóa phân công thất bại: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validatedData = $request->validate([
            'status' => 'required|integer|in:1,2,3,4',
        ]);

        $assignment = Assignment::find($id);

        if (!$assignment) {
            return response()->json([
                'error' => 'Assignment not found.',
            ], 404);
        }

        $assignment->status = $validatedData['status'];
        $assignment->save();

        return response()->json([
            'message' => 'Assignment status updated successfully.',
            'data' => $assignment,
        ], 200);
    }
}
