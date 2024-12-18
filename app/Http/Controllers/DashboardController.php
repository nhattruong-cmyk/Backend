<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Worktime;
use App\Models\Department;
use App\Models\ActivityLog;
use App\Http\Controllers\ActivityLogController;
use App\Models\Task;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        //
    }


    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }

    public function getTotalTaskTime()
    {
        try {
            // Lấy thông tin user hiện tại
            $user = auth()->user();

            // Nếu user có role_id = 1 hoặc 2 (Admin hoặc Manager)
            if (in_array($user->role_id, [1, 2])) {
                // Tính tổng thời gian các task theo từng project
                $projectsTaskTime = DB::table('tasks')
                    ->join('projects', 'tasks.project_id', '=', 'projects.id') // Join bảng projects
                    ->select('tasks.project_id', 'projects.project_name', DB::raw('SUM(tasks.task_time) as total_task_time'))
                    ->groupBy('tasks.project_id', 'projects.project_name')
                    ->get();

                // Tính tổng thời gian cho tất cả các project
                $totalTaskTime = $projectsTaskTime->sum('total_task_time');

                return response()->json([
                    'message' => 'Tổng số giờ của tất cả các task theo project.',
                    'projects_task_time' => $projectsTaskTime,
                    'total_task_time' => $totalTaskTime,
                ], 200);
            }

            // Nếu user có role_id = 3 (Staff)
            elseif ($user->role_id === 3) {
                // Lấy tất cả các phòng ban mà user tham gia
                $userDepartments = DB::table('department_user')
                    ->where('user_id', $user->id)
                    ->pluck('department_id')
                    ->toArray();

                // Lấy tất cả các project liên quan đến các phòng ban mà user tham gia
                $projectIds = DB::table('project_department')
                    ->whereIn('department_id', $userDepartments)
                    ->pluck('project_id')
                    ->toArray();

                // Tính tổng thời gian các task thuộc các project liên quan
                $projectsTaskTime = DB::table('tasks')
                    ->join('projects', 'tasks.project_id', '=', 'projects.id') // Join bảng projects
                    ->select('tasks.project_id', 'projects.project_name', DB::raw('SUM(tasks.task_time) as total_task_time'))
                    ->whereIn('tasks.project_id', $projectIds)
                    ->groupBy('tasks.project_id', 'projects.project_name')
                    ->get();

                // Tính tổng thời gian cho tất cả các project của user
                $totalTaskTime = $projectsTaskTime->sum('total_task_time');

                return response()->json([
                    'message' => 'Tổng số giờ của các task thuộc dự án mà bạn tham gia.',
                    'projects_task_time' => $projectsTaskTime,
                    'total_task_time' => $totalTaskTime,
                ], 200);
            }

            // Trường hợp không đủ quyền
            return response()->json(['error' => 'Bạn không có quyền truy cập thông tin này.'], 403);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Lỗi khi tính tổng thời gian task: ' . $e->getMessage()], 500);
        }
    }

    public function getTaskCountsByStatusGrouped()
    {
        try {
            $user = auth()->user();

            // Define the query base
            $query = DB::table('tasks')
                ->join('projects', 'tasks.project_id', '=', 'projects.id')
                ->select(
                    'projects.id as project_id',
                    'projects.project_name as project_name',
                    'tasks.status',
                    DB::raw('COUNT(tasks.id) as count')
                )
                ->groupBy('projects.id', 'projects.project_name', 'tasks.status');

            // Adjust the query based on the user's role
            if (in_array($user->role_id, [1, 2])) {
                // Admin and Manager can access all projects
            } elseif ($user->role_id === 3) {
                // Staff can only access projects they are part of
                $userDepartments = DB::table('department_user')
                    ->where('user_id', $user->id)
                    ->pluck('department_id')
                    ->toArray();

                $projectIds = DB::table('project_department')
                    ->whereIn('department_id', $userDepartments)
                    ->pluck('project_id')
                    ->toArray();

                $query->whereIn('tasks.project_id', $projectIds);
            } else {
                return response()->json(['error' => 'Bạn không có quyền truy cập thông tin này.'], 403);
            }

            // Execute the query
            $taskCounts = $query->get();

            // Transform the data into the desired structure
            $groupedData = [];
            foreach ($taskCounts as $taskCount) {
                $projectId = $taskCount->project_id;

                if (!isset($groupedData[$projectId])) {
                    $groupedData[$projectId] = [
                        'project_id' => $taskCount->project_id,
                        'project_name' => $taskCount->project_name,
                        'statuses' => [
                            1 => 0,
                            2 => 0,
                            3 => 0,
                            4 => 0,
                        ],
                    ];
                }

                $groupedData[$projectId]['statuses'][$taskCount->status] = $taskCount->count;
            }

            // Format the data as a simple array
            $responseData = array_values($groupedData);

            return response()->json($responseData, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Lỗi khi tính tổng số task theo trạng thái: ' . $e->getMessage()], 500);
        }
    }

    public function getUserTaskStatistics()
    {
        try {
            // Lấy thông tin user hiện tại
            $user = auth()->user();

            // Nếu user có role_id = 1 hoặc 2 (Admin hoặc Manager)
            if (in_array($user->role_id, [1, 2])) {
                // Query để lấy thống kê cho tất cả user
                $userStats = DB::table('users')
                    ->leftJoin('assignments', 'users.id', '=', 'assignments.user_id')
                    ->leftJoin('tasks', 'assignments.task_id', '=', 'tasks.id')
                    ->select(
                        'users.id as user_id',
                        'users.fullname',
                        DB::raw('COUNT(tasks.id) as task_count'),
                        DB::raw('COALESCE(SUM(tasks.task_time), 0) as total_task_time')
                    )
                    ->groupBy('users.id', 'users.fullname')
                    ->get();

                return response()->json([
                    'message' => 'Task statistics retrieved successfully.',
                    'users' => $userStats,
                ], 200);
            }

            // Nếu user có role_id = 3 (Staff), chỉ lấy thống kê của user đó
            elseif ($user->role_id === 3) {
                // Query để lấy thống kê chỉ cho user hiện tại
                $userStats = DB::table('users')
                    ->leftJoin('assignments', 'users.id', '=', 'assignments.user_id')
                    ->leftJoin('tasks', 'assignments.task_id', '=', 'tasks.id')
                    ->select(
                        'users.id as user_id',
                        'users.fullname',
                        DB::raw('COUNT(tasks.id) as task_count'),
                        DB::raw('COALESCE(SUM(tasks.task_time), 0) as total_task_time')
                    )
                    ->where('users.id', $user->id)
                    ->groupBy('users.id', 'users.fullname')
                    ->get();

                return response()->json([
                    'message' => 'Task statistics retrieved for the current user.',
                    'users' => $userStats,
                ], 200);
            }

            // Trường hợp không đủ quyền
            return response()->json(['error' => 'Bạn không có quyền truy cập thông tin này.'], 403);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve task statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getWorktimeWithTasks()
    {
        try {
            $user = auth()->user();
    
            if ($user->role_id === 1 || $user->role_id === 2) {
                $worktimes = Worktime::with(['tasks' => function ($query) {
                    $query->select('id', 'task_name', 'status', 'worktime_id', 'task_time', 'project_id')
                        ->with(['project:id,project_name']); // Sử dụng quan hệ project
                }])->get(['id', 'name', 'status']);
    
                $result = $worktimes->map(function ($worktime) {
                    $totalTaskTime = $worktime->tasks->sum('task_time');
    
                    return [
                        'id_worktime' => $worktime->id,
                        'name' => $worktime->name,
                        'status' => $worktime->status,
                        'total_task_time' => $totalTaskTime,
                        'tasks' => $worktime->tasks->map(function ($task) {
                            return [
                                'id' => $task->id,
                                'task_name' => $task->task_name,
                                'status' => $task->status,
                                'task_time' => $task->task_time,
                                'project_name' => $task->project ? $task->project->project_name : null, // Lấy tên dự án
                            ];
                        }),
                    ];
                });
    
                return response()->json([
                    'message' => 'Thống kê worktime và tasks thành công.',
                    'data' => $result
                ], 200);
            } elseif ($user->role_id === 3) {
                $worktimes = Worktime::whereHas('tasks', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->with(['tasks' => function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->select('id', 'task_name', 'status', 'worktime_id', 'task_time', 'project_id')
                        ->with(['project:id,project_name']);
                }])->get(['id', 'name', 'status']);
    
                $result = $worktimes->map(function ($worktime) {
                    $totalTaskTime = $worktime->tasks->sum('task_time');
    
                    return [
                        'id_worktime' => $worktime->id,
                        'name' => $worktime->name,
                        'status' => $worktime->status,
                        'total_task_time' => $totalTaskTime,
                        'tasks' => $worktime->tasks->map(function ($task) {
                            return [
                                'id' => $task->id,
                                'task_name' => $task->task_name,
                                'status' => $task->status,
                                'task_time' => $task->task_time,
                                'project_name' => $task->project ? $task->project->project_name : null,
                            ];
                        }),
                    ];
                });
    
                return response()->json([
                    'message' => 'Thống kê worktime và tasks cho user hiện tại.',
                    'data' => $result
                ], 200);
            }
    
            return response()->json(['error' => 'Bạn không có quyền truy cập thông tin này.'], 403);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi thống kê worktime và tasks: ' . $e->getMessage()
            ], 500);
        }
    }
    
    

    public function getDepartments(Request $request)
    {
        try {
            $user = auth()->user(); // Lấy thông tin người dùng hiện tại

            if ($user->role_id === 1 || $user->role_id === 2) {
                // Admin hoặc Manager: Đếm tất cả các phòng ban
                $departmentCount = Department::count();
            } elseif ($user->role_id === 3) {
                // Staff: Đếm số lượng phòng ban mà họ tham gia
                $departmentCount = $user->departments()->count(); // Giả sử có quan hệ departments trong model User
            } else {
                // Vai trò không hợp lệ hoặc không được phép truy cập
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json([
                'total_departments' => $departmentCount,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Đã xảy ra lỗi khi thống kê số lượng phòng ban: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getProjects(Request $request)
    {
        try {
            $user = auth()->user();

            if ($user->role_id === 1 || $user->role_id === 2) {
                // Admin hoặc Manager: Đếm tất cả dự án
                $projectCount = Project::count();
            } elseif ($user->role_id === 3) {
                // Staff:

                // Lấy danh sách dự án do họ tạo (dựa vào user_id trong bảng projects)
                $createdProjectIds = Project::where('user_id', $user->id)->pluck('id')->toArray();

                // Lấy các phòng ban mà họ tham gia
                $departmentIds = DB::table('department_user')
                    ->where('user_id', $user->id)
                    ->pluck('department_id');

                // Lấy danh sách dự án liên quan đến các phòng ban mà họ tham gia (dựa vào bảng project_department)
                $relatedProjectIds = DB::table('project_department')
                    ->whereIn('department_id', $departmentIds)
                    ->pluck('project_id')
                    ->toArray();

                // Kết hợp danh sách dự án họ tạo và dự án liên quan
                $allProjectIds = array_unique(array_merge($createdProjectIds, $relatedProjectIds));

                // Đếm số lượng dự án duy nhất
                $projectCount = count($allProjectIds);
            } else {
                // Nếu không phải Admin, Manager hoặc Staff, trả về lỗi Unauthorized
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json([
                'total_projects' => $projectCount,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi tìm nạp dự án: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getUserAssignedTasks(Request $request)
    {
        try {
            $user = auth()->user();

            if ($user->role_id === 1 || $user->role_id === 2) {
                // Admin hoặc Manager: Đếm tất cả nhiệm vụ
                $taskCount = Task::count();
            } elseif ($user->role_id === 3) {
                // Staff: Đếm các nhiệm vụ được giao cho họ (dựa vào user_id trong bảng tasks)
                $taskCount = Task::where('user_id', $user->id)->count();
            } else {
                // Nếu không phải Admin, Manager hoặc Staff, trả về lỗi Unauthorized
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json([
                'total_assigned_tasks' => $taskCount,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi tìm nạp nhiệm vụ: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getUserActivities()
    {
        try {
            // Lấy thông tin user hiện tại
            $user = auth()->user();
    
            if ($user->role_id === 1 || $user->role_id === 2) {
                // Nếu role_id = 1 hoặc 2 (Admin hoặc Manager), lấy danh sách hoạt động của tất cả user
                $logs = ActivityLog::with('user', 'loggable')->get();
            } elseif ($user->role_id === 3) {
                // Nếu role_id = 3 (Staff), chỉ lấy danh sách hoạt động của user đang đăng nhập
                $logs = ActivityLog::with('user', 'loggable')->where('user_id', $user->id)->get();
            } else {
                // Nếu không thuộc role hợp lệ, trả về lỗi Unauthorized
                return response()->json(['error' => 'Unauthorized'], 403);
            }
    
            // Trả về dữ liệu dưới dạng JSON
            return response()->json([
                'message' => 'Danh sách hoạt động được lấy thành công.',
                'logs' => $logs,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi lấy danh sách hoạt động: ' . $e->getMessage(),
            ], 500);
        }
    }
}
