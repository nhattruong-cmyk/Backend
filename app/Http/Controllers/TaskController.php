<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Models\Worktime;
use Illuminate\Support\Facades\Log;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\ActivityLog;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Exception;
use Illuminate\Support\Facades\DB;


use Illuminate\Http\Request;

class TaskController extends Controller
{

    // lấy danh sách Task
    public function index(Request $request)
    {
        $user = $request->user();
    
        // Nếu là Admin hoặc Manager, lấy tất cả các task
        if ($user->role_id === 1 || $user->role_id === 2) {
            $tasks = Task::all();
        }
        // Nếu là Staff (role_id = 3)
        elseif ($user->role_id === 3) {
            if (!is_null($user->create_by)) {
                // Nếu cột create_by không rỗng, lấy các task được giao cho project mà user đó tạo
    
                // Lấy danh sách project_id mà user tạo (dựa vào user_id trong bảng projects)
                $projectIds = Project::where('user_id', $user->id)->pluck('id');
    
                // Lấy task_id từ bảng project_task dựa vào project_id
                $taskIds = DB::table('project_task')->whereIn('project_id', $projectIds)->pluck('task_id');
    
                // Lấy các task tương ứng
                $tasks = Task::whereIn('id', $taskIds)->get();
            } else {
                // Nếu cột create_by rỗng, lấy các task được giao cho phòng ban mà user đó thuộc về
    
                // Lấy danh sách department_id mà user thuộc về (dựa vào bảng department_user)
                $departmentIds = DB::table('department_user')->where('user_id', $user->id)->pluck('department_id');
    
                // Lấy task_id từ bảng task_department dựa vào department_id
                $taskIds = DB::table('task_department')->whereIn('department_id', $departmentIds)->pluck('task_id');
    
                // Lấy các task tương ứng
                $tasks = Task::whereIn('id', $taskIds)->get();
            }
        } else {
            // Nếu không thuộc role nào hợp lệ, trả về lỗi Unauthorized
            return response()->json(['error' => 'Unauthorized'], 403);
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
            $task = Task::with(['projects.departments', 'files', 'comments'])->findOrFail($task_id);

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
                $userDepartments = $user->departments->pluck('id');  // Lấy các ID phòng ban mà user tham gia
                $taskDepartments = collect();  // Khởi tạo collection trống

                // Lấy các departments liên quan đến các projects của task
                foreach ($task->projects as $project) {
                    foreach ($project->departments as $department) {
                        $taskDepartments->push($department->id); // Thêm các ID phòng ban vào collection
                    }
                }

                // Nếu có department nào chung, cho phép xem task
                if ($userDepartments->intersect($taskDepartments)->isNotEmpty()) {
                    return response()->json($task, 200);
                }

                // Nếu không có quyền, trả về Unauthorized
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getTaskDetails($taskId, Request $request)
    {
        $user = $request->user();
        $task = Task::with([
            'comments' => function ($query) {
                $query->whereNull('parent_id')
                    ->orderBy('created_at', 'DESC');
            },
            'comments.user:id,fullname,avatar',
            'comments.files',
            'comments.replies.user:id,fullname,avatar',
            'comments.replies.files',
            'comments.replies.replies.user:id,fullname,avatar',
            'comments.replies.replies.files',
            'comments.replies.replies.replies.user:id,fullname,avatar',
            'comments.replies.replies.replies.files',
            'comments.replies.replies.replies.replies.user:id,fullname,avatar',
            'comments.replies.replies.replies.replies.files',
            'projects.departments.users'
        ])->find($taskId);

        // Xử lý dữ liệu trả về với hàm đệ quy
        $formatComment = function ($comment) use (&$formatComment) {
            $formattedComment = [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'created_at' => $comment->created_at,
                'user' => [
                    'id' => $comment->user->id,
                    'fullname' => $comment->user->fullname,
                    'avatar' => $comment->user->avatar
                ],
                'files' => $comment->files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'file_name' => $file->file_name,
                        'file_path' => $file->file_path
                    ];
                })
            ];

            if ($comment->replies) {
                $formattedComment['replies'] = $comment->replies->map(function ($reply) use ($formatComment) {
                    return $formatComment($reply);
                });
            }

            return $formattedComment;
        };

        $formattedComments = $task->comments->map(function ($comment) use ($formatComment) {
            return $formatComment($comment);
        });

        return response()->json([
            'task' => $task,
            'comments' => $formattedComments
        ]);
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
            // Lấy thông tin người dùng hiện tại
            $user = auth()->user();
    
            // Tìm dự án theo ID
            $project = Project::findOrFail($project_id);
    
            $departments = collect(); // Tạo một tập hợp trống để lưu kết quả
    
            // Logic dựa trên role_id của người dùng
            if ($user->role_id === 1 || $user->role_id === 2) {
                // Admin và Manager: Lấy tất cả các phòng ban thuộc dự án
                $departments = $project->departments()->get();
            } elseif ($user->role_id === 3) {
                if (!is_null($user->create_by)) {
                    // Nếu user có create_by (Staff tạo dự án): Lấy các phòng ban thuộc dự án mà họ tạo
                    $createdProject = Project::where('id', $project->id)
                        ->where('user_id', $user->id) // Kiểm tra dự án được tạo bởi user
                        ->first();
    
                    if ($createdProject) {
                        $departments = $createdProject->departments()->get();
                    }
                } else {
                    // Nếu user không có create_by: Lấy các phòng ban mà họ thuộc về trong dự án đó
                    $departments = $project->departments()
                        ->whereHas('users', function ($query) use ($user) {
                            $query->where('user_id', $user->id); // Kiểm tra user thuộc phòng ban nào
                        })
                        ->get();
                }
            }
    
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
    
    public function getTaskWithoutWorktime(Request $request)
    {
        // Kiểm tra quyền xem task không có worktime_id (sử dụng Policy)
        $this->authorize('viewWithoutWorktime', Task::class);

        $tasks = collect(); // Khởi tạo một collection rỗng

        // Nếu là Admin hoặc Manager, lấy tất cả các task không có worktime_id
        if ($request->user()->role_id === 1 || $request->user()->role_id === 2) {
            $tasks = Task::whereNull('worktime_id')->with(['assignments.user'])->get();
        }

        // Nếu là Staff, lấy các task không có worktime_id mà họ đã tạo hoặc liên quan đến các department họ tham gia
        if ($request->user()->role_id === 3) {
            // Lấy các task mà user đã tạo và không có worktime_id
            $createdTasks = Task::where('user_id', $request->user()->id)
                ->whereNull('worktime_id')
                ->with(['assignments.user'])
                ->get();

            // Lấy tất cả các department mà user tham gia
            $departments = $request->user()->departments;

            // Lấy tất cả task trong các department mà user tham gia và không có worktime_id
            $relatedTasks = $departments->flatMap(function ($department) {
                return $department->tasks()->whereNull('worktime_id')->with(['assignments.user'])->get();
            });

            // Kết hợp các task đã tạo và các task liên quan đến các department mà user tham gia
            $tasks = $createdTasks->merge($relatedTasks)->unique('id');
        }

        // Format lại kết quả để bao gồm thông tin người được assign
        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'task_name' => $task->task_name,
                'description' => $task->description,
                'location_task' => $task->location_task,
                'start_date' => $task->start_date,
                'end_date' => $task->end_date,
                'project_id' => $task->project_id,
                'status' => $task->status,
                'assigned_users' => $task->assignments->map(function ($assignment) {
                    return [
                        'user_id' => $assignment->user->id,
                        'fullname' => $assignment->user->fullname,
                        'avatar' => $assignment->user->avatar,
                        'email' => $assignment->user->email,
                        'department_id' => $assignment->department_id,
                        'taskmaster' => $assignment->taskmaster,
                        'status' => $assignment->status,
                    ];
                }),
            ];
        });

        return response()->json($formattedTasks, 200);
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
            $worktimesAboutToExpire = Worktime::where('end_date', '<=', $currentTime->addDay())
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
                $newWorktime = Worktime::where('start_date', '>', $currentTime)
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

    // Xóa mềm task
    public function destroy($id)
    {
        try {
            // Tìm task theo ID
            $task = Task::findOrFail($id);

            // Lấy thông tin người dùng hiện tại
            $user = auth()->user();

            // Kiểm tra quyền xóa task
            // $this->authorize('delete', $task);  // Kiểm tra quyền xóa task qua TaskPolicy

            // Kiểm tra trạng thái của task
            if (in_array($task->status, ['done'], true)) {
                return response()->json([
                    'error' => 'Không thể xóa nhiệm vụ khi ở trạng thái này.',
                ], 403);  // Không cho phép xóa task khi trạng thái không phải 'to do' hoặc 'done'
            }

            // Kiểm tra quyền của người dùng đối với task
            if ($user->role_id === 1 || $user->role_id === 2) {
                // Admin hoặc Manager có thể xóa mọi task
                $task->delete();
                return response()->json([
                    'message' => 'Xóa nhiệm vụ thành công.',
                    'task' => $task,
                ], 200);
            }

            // Staff chỉ có thể xóa task mà họ tạo hoặc là người được phân công
            if ($user->role_id === 3) {
                if ($task->user_id === $user->id || $task->user_id === $user->id) {
                    // Staff có thể xóa nếu là người tạo hoặc người chủ trì
                    $task->delete();
                    return response()->json([
                        'message' => 'Xóa nhiệm vụ thành công.',
                        'task' => $task,
                    ], 200);
                } else {
                    return response()->json([
                        'error' => 'Xóa nhiệm vụ thất bại: Bạn không có quyền thực hiện hành động này.',
                    ], 403);
                }
            }

            // Nếu không thỏa mãn bất kỳ điều kiện nào trên, không cho phép xóa
            return response()->json([
                'error' => 'Xóa phân công thất bại: Bạn không có quyền thực hiện hành động này.',
            ], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Task not found.',
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'Xóa phân công thất bại: Bạn không có quyền thực hiện hành động này.',
            ], 403);
        } catch (Exception $e) {
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
    public function getTasksByWorktimeId(Request $request, $worktime_id = null)
    {
        // Kiểm tra quyền
        $this->authorize('viewTasksByWorktimeId', [Task::class, $worktime_id]);

        // Kiểm tra worktime_id
        if (is_null($worktime_id)) {
            return response()->json([
                'message' => 'No worktime_id provided.',
                'tasks' => [],
            ], 200);
        }

        // Lấy danh sách tasks và join với assignments + users
        $tasks = Task::with(['assignments.user'])
            ->where('worktime_id', $worktime_id)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'task_name' => $task->task_name,
                    'description' => $task->description,
                    'worktime_id' => $task->worktime_id,
                    'start_date' => $task->start_date,
                    'project_id' => $task->project_id,
                    'end_date' => $task->end_date,
                    'status' => $task->status,
                    'assigned_users' => $task->assignments->map(function ($assignment) {
                        return [
                            'user_id' => $assignment->user->id,
                            'fullname' => $assignment->user->fullname,
                            'avatar' => $assignment->user->avatar,
                            'email' => $assignment->user->email,
                            'department_id' => $assignment->department_id,
                            'taskmaster' => $assignment->taskmaster,
                            'status' => $assignment->status,
                        ];
                    }),
                ];
            });

        return response()->json([
            'worktime_id' => $worktime_id,
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

    public function updateDescription(Request $request, $task_id)
    {
        try {
            // Validate dữ liệu đầu vào
            $request->validate([
                'description' => 'required|string', // Kiểm tra description phải có giá trị và là chuỗi
            ]);

            // Tìm task theo ID
            $task = Task::findOrFail($task_id);

            // Lưu giá trị description mới
            $newDescription = $request->input('description');
            $oldDescription = $task->description;

            // Cập nhật mô tả của task
            $task->update(['description' => $newDescription]);

            // Ghi lại lịch sử thay đổi mô tả
            ActivityLog::create([
                'user_id' => Auth::user()->id, // ID của người thực hiện
                'loggable_id' => $task->id, // ID của task
                'loggable_type' => 'App\Models\Task', // Loại đối tượng
                'action' => 'updated', // Hành động cập nhật
                'changes' => json_encode([
                    'description' => [
                        'old' => $oldDescription,
                        'new' => $newDescription,
                    ],
                ]), // Ghi lại thay đổi mô tả
            ]);

            // Trả về JSON response với task đã cập nhật
            return response()->json([
                'message' => 'Task description updated successfully!',
                'task' => $task,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation error.',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while updating task description.',
                'details' => $e->getMessage(),
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
        // Tìm các task có worktime với status 'running' hoặc '2'
        $tasks = Task::whereHas('worktime', function ($query) {
            $query->where('status', 'running') // Sửa chính tả 'runing' -> 'running'
                ->orWhere('status', '2');
        })
            ->with(['assignments.user']) // Load quan hệ assignments và user
            ->distinct() // Loại bỏ các kết quả trùng lặp
            ->get();

        // Định dạng lại các task trước khi trả về
        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'task_name' => $task->task_name,
                'task_time' => $task->task_time,
                'worktime_id' => $task->worktime_id,
                'description' => $task->description,
                'location_task' => $task->location_task,
                'user_id' => $task->user_id,
                'project_id' => $task->project_id,
                'start_date' => $task->start_date,
                'end_date' => $task->end_date,
                'status' => $task->status,
                'assigned_users' => $task->assignments->map(function ($assignment) {
                    return [
                        'user_id' => $assignment->user->id,
                        'fullname' => $assignment->user->fullname,
                        'email' => $assignment->user->email,
                        'avatar' => $assignment->user->avatar,
                        'department_id' => $assignment->department_id,
                        'taskmaster' => $assignment->taskmaster,
                        'status' => $assignment->status,
                    ];
                }),
            ];
        });

        return response()->json($formattedTasks, 200);
    }

    public function getTaskWithUser($taskId)
    {
        try {
            // Lấy thông tin task theo ID
            $task = Task::findOrFail($taskId);

            // Lấy tất cả người dùng được phân công cho task
            $assignedUsers = Assignment::where('task_id', $taskId)
                ->with('user') // Kết hợp thông tin người dùng
                ->get()
                ->map(function ($assignment) {
                    return $assignment->user; // Trả về thông tin người dùng (có thể thêm các trường khác nếu cần)
                });

            // Trả về kết quả dưới dạng JSON
            return response()->json([
                'task' => $task,
                'assigned_users' => $assignedUsers
            ], 200);
        } catch (\Exception $e) {
            // Trả về lỗi nếu có bất kỳ ngoại lệ nào
            return response()->json([
                'error' => 'Không tìm thấy task hoặc có lỗi khi lấy dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }
}
