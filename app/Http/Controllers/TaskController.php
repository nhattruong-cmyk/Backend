<?php

namespace App\Http\Controllers;
use App\Models\Task;
use App\Models\Project;
use App\Models\Department;



use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       $tasks = Task::with('projects', 'departments')->get();
       return response()->json($tasks, 200);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Xác thực dữ liệu đầu vào mà không dùng 'exists' cho department_id
        $validatedData = $request->validate([
            'task_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|integer|in:1,2,3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_id' => 'required|exists:projects,id',
            'department_id' => 'required|integer',  // Không dùng exists ở đây
        ]);
    
        try {
            // Lấy thông tin project hiện tại và các departments thuộc project đó
            $project = Project::with('departments')->findOrFail($validatedData['project_id']);
            
            // Lấy danh sách department hợp lệ thuộc về project
            $validDepartmentIds = $project->departments->pluck('id')->toArray();
    
            // Kiểm tra xem department_id có tồn tại trong bảng departments hay không
            $department = Department::find($validatedData['department_id']);
            if (!$department) {
                return response()->json([
                    'error' => 'The selected department does not exist in the database.'
                ], 400);
            }
    
            // Kiểm tra nếu department_id không nằm trong danh sách các phòng ban của project
            if (!in_array($validatedData['department_id'], $validDepartmentIds)) {
                return response()->json([
                    'error' => 'The selected department does not belong to the specified project.'
                ], 400);
            }
    
            // Tạo một nhiệm vụ mới (task)
            $task = Task::create($validatedData);
    
            // Gán department cho task trong bảng pivot task_department
            $task->departments()->attach($validatedData['department_id']);
    
            // Gán task cho project trong bảng pivot project_task
            $project->tasks()->attach($task->id);
    
            return response()->json($task->load('departments'), 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create task: ' . $e->getMessage()
            ], 500);
        }
    }
    
    
    /**
     * Display the specified resource.
     */
    public function show($task_id)
    {
        // Tải trước mối quan hệ project và departments của project
        $task = Task::with('project.departments')->find($task_id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        return response()->json($task, 200);
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $task_id)
    {
        // Tìm nhiệm vụ theo ID và lấy cả project cùng các departments thuộc project đó
        $task = Task::findOrFail($task_id);
    
        // Kiểm tra nếu task không có project liên kết
        if (!$task->project) {
            return response()->json(['error' => 'This task is not associated with any project.'], 400);
        }
    
        // Lấy project liên kết với task và nạp trước quan hệ departments
        $project = $task->project()->with('departments')->first();
    
        // Xác thực dữ liệu đầu vào
        $validatedData = $request->validate([
            'task_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|integer|in:1,2,3', // Sử dụng số để xác thực
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_id' => 'sometimes|required|exists:projects,id',
            'department_id' => 'sometimes|integer' // Xác thực department_id nếu có cung cấp
        ]);
    
        try {
            // Kiểm tra tính hợp lệ của `department_id` nếu được cung cấp
            if (isset($validatedData['department_id'])) {
                $departmentId = $validatedData['department_id'];
    
                // Lấy danh sách `department_id` hợp lệ thuộc `project`
                $validDepartments = $project->departments->pluck('id')->toArray();
    
                // Kiểm tra nếu `department_id` không thuộc `project`
                if (!in_array($departmentId, $validDepartments)) {
                    return response()->json([
                        'error' => 'The department is not associated with the project that the task belongs to.'
                    ], 400);
                }
    
                // Cập nhật liên kết giữa task và department trong bảng phụ `task_department`
                $task->departments()->sync([$departmentId]);
            }
    
            // Cập nhật các thông tin khác của nhiệm vụ nếu được cung cấp
            $task->update($validatedData);
    
            return response()->json(['message' => 'Task updated successfully', 'task' => $task->load('departments')], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update task: ' . $e->getMessage()], 500);
        }
    }
    
    
    
    
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($task_id)
    {
        // Tìm nhiệm vụ theo ID
        $task = Task::find($task_id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        // Xóa nhiệm vụ
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully'], 200);
    }
}
