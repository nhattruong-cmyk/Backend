<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Worktimes;
use Carbon\Carbon;
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
            // Lấy thông tin user hiện tại
            $user = auth()->user();
    
            // Nếu user có role_id = 1 hoặc 2 (Admin hoặc Manager)
            if (in_array($user->role_id, [1, 2])) {
                // Lấy tất cả worktime cùng với các task liên quan
                $worktimes = Worktimes::with(['tasks' => function ($query) {
                    $query->select('id', 'task_name', 'status', 'worktime_id', 'task_time'); // Thêm cột task_time
                }])->get(['id', 'name', 'status']); // Lấy cột cần thiết từ worktime
    
                // Định dạng lại dữ liệu theo yêu cầu
                $result = $worktimes->map(function ($worktime) {
                    // Tính tổng task_time của tất cả các task trong worktime này
                    $totalTaskTime = $worktime->tasks->sum('task_time');
    
                    return [
                        'id_worktime' => $worktime->id,
                        'name' => $worktime->name,
                        'status' => $worktime->status,
                        'total_task_time' => $totalTaskTime, // Thêm tổng thời gian của các task
                        'tasks' => $worktime->tasks->map(function ($task) {
                            return [
                                'id' => $task->id,
                                'task_name' => $task->task_name,
                                'status' => $task->status,
                                'task_time' => $task->task_time, // Trả về task_time cho từng task
                            ];
                        }),
                    ];
                });
    
                return response()->json([
                    'message' => 'Thống kê worktime và tasks thành công.',
                    'data' => $result
                ], 200);
            }
    
            // Nếu user có role_id = 3 (Staff), chỉ lấy worktime và task của user đó
            elseif ($user->role_id === 3) {
                // Lấy worktime của user hiện tại và các task liên quan
                $worktimes = Worktimes::whereHas('tasks', function ($query) use ($user) {
                    $query->where('user_id', $user->id); // Lọc task theo user_id
                })->with(['tasks' => function ($query) use ($user) {
                    $query->where('user_id', $user->id) // Lọc task theo user_id
                          ->select('id', 'task_name', 'status', 'worktime_id', 'task_time');
                }])->get(['id', 'name', 'status']); // Lấy cột cần thiết từ worktime
    
                // Định dạng lại dữ liệu theo yêu cầu
                $result = $worktimes->map(function ($worktime) {
                    // Tính tổng task_time của tất cả các task trong worktime này
                    $totalTaskTime = $worktime->tasks->sum('task_time');
    
                    return [
                        'id_worktime' => $worktime->id,
                        'name' => $worktime->name,
                        'status' => $worktime->status,
    'total_task_time' => $totalTaskTime, // Thêm tổng thời gian của các task
                        'tasks' => $worktime->tasks->map(function ($task) {
                            return [
                                'id' => $task->id,
                                'task_name' => $task->task_name,
                                'status' => $task->status,
                                'task_time' => $task->task_time, // Trả về task_time cho từng task
                            ];
                        }),
                    ];
                });
    
                return response()->json([
                    'message' => 'Thống kê worktime và tasks cho user hiện tại.',
                    'data' => $result
                ], 200);
            }
    
            // Trường hợp không đủ quyền
            return response()->json(['error' => 'Bạn không có quyền truy cập thông tin này.'], 403);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi thống kê worktime và tasks: ' . $e->getMessage()
            ], 500);
        }
    }
}
