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

    public function index()
    {
        $tasks = Task::with('projects', 'departments', 'files')->get();
        return response()->json($tasks, 200);
    }

    public function store(StoreTaskRequest $request)
    {
        try {
            // Lấy dữ liệu đã xác thực từ StoreTaskRequest
            $validatedData = $request->validated();

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

    public function show($task_id)
    {
        // Tải trước mối quan hệ project và departments của project
        $task = Task::with('projects.departments', 'files')->find($task_id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        return response()->json($task, 200);
    }

    public function update(UpdateTaskRequest $request, $task_id)
    {
        // Tìm task theo ID và lấy cả các projects liên kết
        $task = Task::with('projects', 'departments', 'files')->findOrFail($task_id);

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

    public function getTaskWithoutWorktime()
    {
        $tasks = Task::with('projects', 'departments', 'files')->whereNull('worktime_id')->get();
        return response()->json($tasks, 200);
    }

    public function updateLocationTask(Request $request, $task_id)
    {
        try {
            // Validate dữ liệu đầu vào
            $request->validate([
                'location_task' => 'sometimes|required|integer|in:0,1,2', // Giá trị vị trí 0,1,2
            ]);

            // Tìm task theo ID
            $task = Task::findOrFail($task_id);

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

    public function moveTasksToAnotherWorktime()
    {
        try {
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

    public function destroy($id)
    {
        try {
            // Tìm task theo ID
            $task = Task::findOrFail($id);

            // Thực hiện xóa mềm
            $task->delete();

            return response()->json(['message' => 'Task soft deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to soft delete task: ' . $e->getMessage()], 500);
        }
    }

    public function restore($id)
    {
        try {
            // Tìm task đã xóa mềm
            $task = Task::onlyTrashed()->findOrFail($id);

            // Khôi phục task
            $task->restore();

            return response()->json(['message' => 'Task restored successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore task: ' . $e->getMessage()], 500);
        }
    }

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

    public function forceDelete($id)
    {
        try {
            // Tìm task đã bị xóa mềm
            $task = Task::onlyTrashed()->findOrFail($id);

            // Thực hiện xóa cứng
            $task->forceDelete();

            return response()->json(['message' => 'Task permanently deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to permanently delete task: ' . $e->getMessage()], 500);
        }
    }
}
