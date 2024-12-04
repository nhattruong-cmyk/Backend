<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Models\Worktimes;
use Illuminate\Support\Facades\Log;

use App\Models\Department;
use App\Models\ActivityLog;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Exception;

use Illuminate\Http\Request;

class TaskController extends Controller
{

    // lấy danh sách Task
    public function index(Request $request)
    {
        // Kiểm tra quyền xem task (Sử dụng Policy)
        $this->authorize('viewAny', Task::class);

        // Nếu là Admin hoặc Manager, lấy tất cả các task
        if ($request->user()->role_id === 1 || $request->user()->role_id === 2) {
            $tasks = Task::all();
        }

        // Nếu là Staff, lấy các task mà họ đã tạo và các task liên quan tới department mà họ tham gia
        if ($request->user()->role_id === 3) {
            // Lấy các task mà user đã tạo (dựa vào user_id)
            $createdTasks = Task::where('user_id', $request->user()->id)->get();

            // Lấy tất cả các department mà user tham gia
            $departments = $request->user()->departments;

            // Lấy tất cả task trong các department mà user tham gia
            $relatedTasks = $departments->flatMap(function ($department) {
                return $department->tasks;
            });

            // Kết hợp các task đã tạo và các task liên quan đến các department mà user tham gia
            $tasks = $createdTasks->merge($relatedTasks)->unique('id');
        }

        return response()->json($tasks);
    }

    public function getTasksByProject($projectId)
    {
        try {
            // Lấy tất cả task thuộc project_id
            $tasks = Task::where('project_id', $projectId)->get();

            if ($tasks->isEmpty()) {
                return response()->json(['message' => 'Không thấy nhiệm vụ nào trong dự án này'], 404);
            }

            return response()->json(['tasks' => $tasks], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi không thấy nhiệm vụ: ' . $e->getMessage()], 500);
        }
    }

    // tạo mới task
    public function store(StoreTaskRequest $request)
    {
        try {
            // Kiểm tra quyền tạo task (sử dụng Policy)
            $this->authorize('create', Task::class);  // Kiểm tra quyền của người dùng với Task

            // Lấy dữ liệu đã xác thực từ StoreTaskRequest
            $validatedData = $request->validated();
            // Thêm user_id của người đăng nhập vào dữ liệu được xác thực
            $validatedData['user_id'] = Auth::id();

            // Lấy thông tin project hiện tại và các departments thuộc project đó
            $project = Project::with('departments')->findOrFail($validatedData['project_id']);

            // Lấy danh sách department hợp lệ thuộc về project
            $validDepartmentIds = $project->departments->pluck('id')->toArray();

            // Kiểm tra xem department_id có tồn tại trong bảng departments hay không
            $department = Department::find($validatedData['department_id']);
            if (!$department) {
                return response()->json([
                    'error' => 'Phòng ban không hợp lệ!'
                ], 400);
            }

            // Kiểm tra nếu department_id không nằm trong danh sách các phòng ban của project
            if (!in_array($validatedData['department_id'], $validDepartmentIds)) {
                return response()->json([
                    'error' => 'Phòng ban này không thuộc dự án, vui lòng kiểm tra lại!'
                ], 400);
            }

            // Tạo một nhiệm vụ mới (task)
            $task = Task::create($validatedData);

            // Gán department cho task trong bảng pivot task_department
            $task->departments()->attach($validatedData['department_id']);

            // Gán task cho project trong bảng pivot project_task
            $project->tasks()->attach($task->id);

            // Ghi lại lịch sử hoạt động sau khi tạo task thành công
            ActivityLog::create([
                'user_id' => Auth::user()->id, // Người thực hiện
                'loggable_id' => $task->id, // ID của task vừa được tạo
                'loggable_type' => 'App\Models\Task', // Loại đối tượng (Task)
                'action' => 'created', // Hành động được thực hiện (tạo task)
                'changes' => json_encode($validatedData), // Lưu lại dữ liệu vừa được gửi
            ]);

            // Xử lý upload file nếu có
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    // Lưu file vào thư mục 'files' và lấy đường dẫn
                    $filePath = $file->store('files', 'public');

                    // Lưu thông tin file vào bảng files
                    File::create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $filePath,
                        'task_id' => $task->id,
                        'uploaded_by' => Auth::id(), // Sử dụng auth() thay cho Auth::user()
                    ]);
                }
            }

            // Trả về kết quả với thông tin task và các file liên quan
            return response()->json([
                'message' => 'Task created successfully!',
                'task' => $task->load('departments', 'files')  // Load thêm department và files liên quan
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to create task: ' . $e->getMessage()
            ], 500);
        }
    }

    // xem thôg tin task
    public function show($task_id)
    {
        try {
            // Tìm task theo ID và tải các mối quan hệ như project, departments, và files
            $task = Task::with(['projects.departments', 'files'])->findOrFail($task_id);

            // Kiểm tra quyền của người dùng đối với task (sử dụng Policy)
            $this->authorize('view', $task); // Kiểm tra quyền xem task

            // Kiểm tra quyền của người dùng (tùy theo role_id)
            $user = auth()->user();

            // Admin và Manager có thể xem bất kỳ task nào
            if ($user->role_id === 1 || $user->role_id === 2) {
                return response()->json($task, 200);
            }

            // Staff (role_id = 3): Có thể xem task nếu họ đã tạo task đó hoặc task liên quan đến department mà họ tham gia
            if ($user->role_id === 3) {
                // Kiểm tra nếu task được tạo bởi user này
                if ($task->user_id === $user->id) {
                    return response()->json($task, 200);
                }

                // Kiểm tra nếu task liên quan đến department mà user tham gia
                $userDepartments = $user->departments->pluck('id');
                $taskDepartments = $task->projects->pluck('departments.id');

                // Nếu có department nào chung, cho phép xem task
                if ($userDepartments->intersect($taskDepartments)->isNotEmpty()) {
                    return response()->json($task, 200);
                }

                // Nếu không có quyền, trả về Unauthorized
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Trường hợp khác (không đủ quyền), trả về Unauthorized
            return response()->json(['message' => 'Unauthorized'], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Task not found'], 404); // 404 Task not found
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch task details: ' . $e->getMessage()], 500); // 500 Server error
        }
    }

    // cập nhật task
    public function update(UpdateTaskRequest $request, $task_id)
    {
        // Tìm task theo ID và lấy cả các projects liên kết
        $task = Task::with('projects', 'departments', 'files')->findOrFail($task_id);

        // Phân quyền bằng Policy
        $this->authorize('update', $task); // Đây là nơi bạn sẽ kiểm tra quyền trong TaskPolicy

        try {
            // Lấy dữ liệu đã xác thực từ `UpdateTaskRequest`
            $validatedData = $request->validated();

            // Mảng lưu trữ các thay đổi để ghi lại lịch sử
            $changes = [];

            // Kiểm tra và cập nhật mối quan hệ `project_id` nếu được cung cấp
            if (isset($validatedData['project_id'])) {
                // Lấy project mới theo ID và nạp các departments của nó
                $newProject = Project::with('departments')->findOrFail($validatedData['project_id']);

                // Nếu có department_id, kiểm tra department có thuộc project không
                if (isset($validatedData['department_id'])) {
                    $departmentId = $validatedData['department_id'];
                    $validDepartments = $newProject->departments->pluck('id')->toArray();

                    if (!in_array($departmentId, $validDepartments)) {
                        return response()->json(['error' => 'The department does not belong to the project that the task is being updated to.'], 400);
                    }
                }

                // Cập nhật bảng `task_project` thông qua phương thức `sync`
                $task->projects()->sync([$validatedData['project_id']]);

                // Nếu có department_id, cập nhật bảng `task_department`
                if (isset($validatedData['department_id'])) {
                    $task->departments()->sync([$validatedData['department_id']]);
                }

                // Ghi lại thay đổi project_id
                $changes['project_id'] = $validatedData['project_id'];
            }

            // Ghi lại các thay đổi trong task (ngoài project_id và department_id)
            foreach ($validatedData as $key => $value) {
                if ($task->$key !== $value) {
                    $changes[$key] = $value;
                }
            }

            // Cập nhật các thông tin khác của task nếu được cung cấp
            $task->update($validatedData);

            // Xóa file cũ nếu có yêu cầu
            if (isset($validatedData['delete_file_ids'])) {
                $filesToDelete = File::whereIn('id', $validatedData['delete_file_ids'])->get();
                foreach ($filesToDelete as $file) {
                    Storage::delete($file->file_path); // Xóa file khỏi hệ thống lưu trữ
                    $file->delete(); // Xóa record trong database
                }
            }

            // Upload file mới nếu có
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $filePath = $file->store('files', 'public');
                    File::create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $filePath,
                        'task_id' => $task->id,
                        'uploaded_by' => Auth::id(),
                    ]);
                }
            }

            // Lưu lịch sử hoạt động sau khi cập nhật task
            if (!empty($changes)) {
                ActivityLog::create([
                    'user_id' => Auth::user()->id, // ID của người thực hiện
                    'loggable_id' => $task->id, // ID của task
                    'loggable_type' => 'App\Models\Task', // Loại đối tượng
                    'action' => 'updated', // Hành động là cập nhật
                    'changes' => json_encode($changes), // Ghi lại những thay đổi
                ]);
            }

            // Trả về task đã cập nhật, kèm theo project, department và files liên quan
            return response()->json([
                'message' => 'Task updated successfully!',
                'task' => $task->load('projects', 'departments', 'files')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to update task: ' . $e->getMessage()
            ], 500);
        }
    }

    // lấy danh sách phòng ban theo project
    public function getDepartmentsByProjectId($project_id)
    {
        try {
            // Tìm dự án theo ID và nạp các phòng ban (departments) liên kết
            $project = Project::with('departments')->findOrFail($project_id);

            // Lấy danh sách các phòng ban của dự án
            $departments = $project->departments;

            return response()->json([
                'message' => 'Departments retrieved successfully',
                'departments' => $departments
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve departments: ' . $e->getMessage()
            ], 500);
        }
    }

    // lấy danh sách task không có worktime
    public function getTaskWithoutWorktime()
    {
        $tasks = Task::with('projects', 'departments', 'files')->whereNull('worktime_id')->get();
        return response()->json($tasks, 200);
    }

    // cập nhật vị trí task
    public function updateLocationTask(Request $request, $task_id)
    {
        try {
            // Validate dữ liệu đầu vào
            $request->validate([
                'location_task' => 'sometimes|required|integer|in:0,1,2', // Giá trị vị trí 0,1,2
            ]);

            // Tìm task theo ID
            $task = Task::findOrFail($task_id);

            // Kiểm tra quyền của người dùng để cập nhật task
            $this->authorize('update', $task); // Sử dụng phân quyền update từ TaskPolicy

            // Lấy giá trị location_task mới
            $newLocationTask = $request->input('location_task');

            // Kiểm tra nếu có thay đổi location_task
            if ($task->location_task !== $newLocationTask) {
                $oldLocationTask = $task->location_task;

                // Cập nhật location_task
                $task->update(['location_task' => $newLocationTask]);

                // Ghi lại lịch sử thay đổi
                ActivityLog::create([
                    'user_id' => Auth::user()->id, // ID của người thực hiện
                    'loggable_id' => $task->id, // ID của task
                    'loggable_type' => 'App\Models\Task', // Loại đối tượng
                    'action' => 'updated', // Hành động cập nhật
                    'changes' => json_encode([
                        'location_task' => [
                            'old' => $oldLocationTask,
                            'new' => $newLocationTask,
                        ],
                    ]), // Ghi lại thay đổi location_task
                ]);
            }

            // Trả về JSON response với task đã cập nhật
            return response()->json([
                'message' => 'Task location_task updated successfully!',
                'task' => $task,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation error.',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update task location_task: ' . $e->getMessage(),
            ], 500);
        }
    }

    // di chuyển task
    public function moveTasksToAnotherWorktime()
    {
        try {
            // Kiểm tra quyền của người dùng để di chuyển task
            $this->authorize('moveTasksToAnotherWorktime', Task::class);

            // Lấy thời gian hiện tại
            $currentTime = now();

            // Lấy danh sách các worktime gần hết hạn (ví dụ: hết hạn trong vòng 1 ngày)
            $worktimesAboutToExpire = Worktimes::where('end_date', '<=', $currentTime->addDay())
                ->whereHas('tasks', function ($query) {
                    $query->where('status', '!=', 'completed'); // Lọc các task chưa hoàn thành
                })
                ->get();

            if ($worktimesAboutToExpire->isEmpty()) {
                return response()->json([
                    'message' => 'No worktimes with pending tasks about to expire found.',
                ], 404);
            }

            // Duyệt qua từng worktime gần hết hạn
            foreach ($worktimesAboutToExpire as $worktime) {
                // Lấy danh sách task chưa hoàn thành trong worktime
                $pendingTasks = $worktime->tasks()->where('status', '!=', 'completed')->get();

                // Tìm một worktime mới phù hợp (ví dụ: worktime bắt đầu sau ngày hiện tại)
                $newWorktime = Worktimes::where('start_date', '>', $currentTime)
                    ->where('end_date', '>', $worktime->end_date) // Phải có thời gian kết thúc sau worktime cũ
                    ->first();

                if (!$newWorktime) {
                    // Nếu không tìm thấy worktime phù hợp, bỏ qua task
                    Log::warning("No suitable worktime found for tasks in worktime ID {$worktime->id}");
                    continue;
                }

                // Chuyển từng task qua worktime mới
                foreach ($pendingTasks as $task) {
                    $task->update(['worktime_id' => $newWorktime->id]);

                    // Ghi lại lịch sử hoạt động
                    ActivityLog::create([
                        'user_id' => Auth::user()->id, // Người thực hiện
                        'loggable_id' => $task->id,   // Task được cập nhật
                        'loggable_type' => 'App\Models\Task',
                        'action' => 'moved',          // Hành động là chuyển task
                        'changes' => json_encode([
                            'worktime_id' => [
                                'old' => $worktime->id,
                                'new' => $newWorktime->id,
                            ],
                        ]),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Tasks moved to suitable worktimes successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to move tasks: ' . $e->getMessage(),
            ], 500);
        }
    }

    // xóa mềm task
    public function destroy($task_id)
    {
        try {
            // Tìm task theo ID
            $task = Task::findOrFail($task_id);
    
            // Phân quyền xóa task
            $this->authorize('delete', $task); // Gọi phân quyền trong TaskPolicy
    
            // Kiểm tra trạng thái của task (nếu cần)
            if ($task->status === 'completed') {
                return response()->json([
                    'error' => 'Completed tasks cannot be deleted.'
                ], 403); // Không cho phép xóa task đã hoàn thành
            }
    
            // Xóa mềm task
            $task->delete();
    
            return response()->json([
                'message' => 'Task soft-deleted successfully.',
                'task' => $task, // Trả về thông tin task đã xóa
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Nếu không tìm thấy task
            return response()->json([
                'error' => 'Task not found.',
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Nếu không đủ quyền
            return response()->json([
                'error' => 'You do not have permission to delete this task.',
            ], 403);
        } catch (Exception $e) {
            // Bắt lỗi chung
            return response()->json([
                'error' => 'Failed to soft-delete task: ' . $e->getMessage(),
            ], 500);
        }
    }
    

    // khôi phục task đã xóa mềm
    public function restore($task_id)
    {
        try {
            // Tìm task đã bị xóa mềm
            $task = Task::onlyTrashed()->findOrFail($task_id);

            // Kiểm tra phân quyền khôi phục
            $this->authorize('restore', $task);

            // Khôi phục task
            $task->restore();

            return response()->json(['message' => 'Task restored successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore task: ' . $e->getMessage()], 500);
        }
    }

    // lấy danh sách task đã xóa mềm
    public function getTrashed()
    {
        try {
            // Lấy danh sách task đã xóa mềm
            $trashedTasks = Task::onlyTrashed()->get();

            if ($trashedTasks->isEmpty()) {
                return response()->json(['message' => 'No trashed tasks found'], 404);
            }

            return response()->json(['data' => $trashedTasks], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve trashed tasks: ' . $e->getMessage()], 500);
        }
    }

    // xóa cứng task
    public function forceDelete($task_id)
    {
        try {
            // Tìm task đã bị xóa mềm
            $task = Task::onlyTrashed()->findOrFail($task_id);

            // Kiểm tra phân quyền xóa cứng
            $this->authorize('forceDelete', $task);

            // Kiểm tra xem task có còn liên kết với các department hay không
            if ($task->departments()->count() > 0) {
                return response()->json(['error' => 'Cannot permanently delete task because it is associated with departments.'], 400);
            }

            // Thực hiện xóa cứng
            $task->forceDelete();

            return response()->json(['message' => 'Task permanently deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to permanently delete task: ' . $e->getMessage()], 500);
        }
    }

    // lấy danh sách task từ worktime_id
    public function getTasksByWorktimeId($id = null)
    {
        if (is_null($id)) {
            return response()->json([
                'message' => 'No worktime_id provided.',
                'tasks' => [],
            ], 200);
        }

        $tasks = Task::where('worktime_id', $id)
            ->with('assignments')
            ->get();

        if ($tasks->isEmpty()) {
            return response()->json([
                'message' => 'No tasks found for this worktime_id.',
                'tasks' => [],
            ], 200);
        }

        return response()->json([
            'worktime_id' => $id,
            'tasks' => $tasks,
        ], 200);
    }

    // cập nhật worktime_id (có thể rỗng)
    public function updateWorktimeId(Request $request, $task_id)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validatedData = $request->validate([
                'worktime_id' => 'nullable|integer|exists:worktimes,id', // Phải là số nguyên hoặc null
            ]);

            // Tìm task theo ID
            $task = Task::findOrFail($task_id);

            // Lưu giá trị worktime_id cũ để ghi lịch sử nếu cần
            $oldWorktimeId = $task->worktime_id;

            // Cập nhật worktime_id mới (cho phép null)
            $task->update(['worktime_id' => $validatedData['worktime_id']]);

            // Ghi lại lịch sử nếu cần
            ActivityLog::create([
                'user_id' => Auth::id(),
                'loggable_id' => $task->id,
                'loggable_type' => 'App\Models\Task',
                'action' => 'updated',
                'changes' => json_encode([
                    'worktime_id' => [
                        'old' => $oldWorktimeId,
                        'new' => $validatedData['worktime_id'],
                    ],
                ]),
            ]);

            // Trả về thông báo thành công
            return response()->json([
                'message' => 'Worktime updated successfully for task.',
                'task' => $task,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Trả về lỗi xác thực
            return response()->json([
                'error' => 'Validation error.',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Trả về lỗi hệ thống
            return response()->json([
                'error' => 'Failed to update worktime_id: ' . $e->getMessage(),
            ], 500);
        }
    }

    // chỉ update riêng trường status
    public function updateStatus(Request $request, $task_id)
    {
        try {
            // Validate dữ liệu đầu vào
            $request->validate([
                'status' => 'sometimes|required|integer|in:1,2,3,4', // Các trạng thái hợp lệ
            ]);

            // Tìm task theo ID
            $task = Task::findOrFail($task_id);

            // Lưu trạng thái mới vào task
            $newStatus = $request->input('status');
            $oldStatus = $task->status;

            // Cập nhật trạng thái
            $task->update(['status' => $newStatus]);

            // Ghi lại lịch sử thay đổi trạng thái
            ActivityLog::create([
                'user_id' => Auth::user()->id, // ID của người thực hiện
                'loggable_id' => $task->id, // ID của task
                'loggable_type' => 'App\Models\Task', // Loại đối tượng
                'action' => 'updated', // Hành động cập nhật
                'changes' => json_encode([
                    'status' => [
                        'old' => $oldStatus,
                        'new' => $newStatus,
                    ],
                ]), // Ghi lại thay đổi trạng thái
            ]);

            // Trả về JSON response với task đã cập nhật
            return response()->json([
                'message' => 'Task status updated successfully!',
                'task' => $task,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation error.',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update task status: ' . $e->getMessage(),
            ], 500);
        }
    }
    // chỉ update riêng trường task_time
    public function updateTaskTime(Request $request, $task_id)
    {
        try {
            // Validate dữ liệu đầu vào
            $request->validate([
                'task_time' => 'sometimes|nullable|numeric', // Kiểm tra task_time là số và có thể null
            ]);

            // Tìm task theo ID
            $task = Task::findOrFail($task_id);

            // Lấy giá trị task_time mới
            $newTaskTime = $request->input('task_time');

            // Kiểm tra nếu có thay đổi task_time
            if ($task->task_time !== $newTaskTime) {
                $oldTaskTime = $task->task_time;

                // Cập nhật task_time
                $task->update(['task_time' => $newTaskTime]);

                // Ghi lại lịch sử thay đổi
                ActivityLog::create([
                    'user_id' => Auth::user()->id, // ID của người thực hiện
                    'loggable_id' => $task->id, // ID của task
                    'loggable_type' => 'App\Models\Task', // Loại đối tượng
                    'action' => 'updated', // Hành động cập nhật
                    'changes' => json_encode([
                        'task_time' => [
                            'old' => $oldTaskTime,
                            'new' => $newTaskTime,
                        ],
                    ]), // Ghi lại thay đổi task_time
                ]);
            }

            // Trả về JSON response với task đã cập nhật
            return response()->json([
                'message' => 'Task time updated successfully!',
                'task' => $task,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation error.',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update task time: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getRunningTasks()
    {
        $tasks = Task::whereHas('worktime', function ($query) {
            $query->where('status', 'runing')
                ->orWhere('status', '2');
        })->get();  

        return response()->json($tasks);
    }
    
}
