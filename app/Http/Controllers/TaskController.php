<?php

namespace App\Http\Controllers;
use App\Models\Task;
use App\Models\Project;


use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       $tasks = Task::with('project', 'departments')->get();
       return response()->json($tasks, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $validatedData = $request->validate([
            'task_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|integer|in:1,2,3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_id' => 'required|exists:projects,id',
            'department_ids' => 'required|array',
            'department_ids.*' => 'exists:departments,id',
        ]);
    
        try {
            // Lấy thông tin project hiện tại
            $project = Project::with('departments')->findOrFail($validatedData['project_id']);
    
            // Lấy danh sách phòng ban hợp lệ (phòng ban thuộc về dự án)
            $validDepartmentIds = $project->departments->pluck('id')->toArray();
    
            // Kiểm tra phòng ban được gán có nằm trong project không
            $invalidDepartments = array_diff($validatedData['department_ids'], $validDepartmentIds);
            if (!empty($invalidDepartments)) {
                return response()->json(['error' => 'Some departments are not associated with the project'], 400);
            }
    
            // Tạo một nhiệm vụ mới
            $task = Task::create($validatedData);
    
            if ($task) {
                // Gán các phòng ban hợp lệ cho nhiệm vụ
                $task->departments()->sync($validatedData['department_ids']);
    
                return response()->json($task->load('departments'), 201);
            } else {
                return response()->json(['error' => 'Failed to create task'], 500);
            }
    
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create task: ' . $e->getMessage()], 500);
        }
    }
    
    
    

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // Tải trước mối quan hệ project và departments của project
        $task = Task::with('project.departments')->findOrFail($id);
    
        return response()->json($task, 200);
    }
    

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Tìm nhiệm vụ theo ID
        $task = Task::findOrFail($id);
    
        // Xác thực dữ liệu đầu vào
        $validatedData = $request->validate([
            'task_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|integer|in:1,2,3', // Sử dụng số để xác thực
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_id' => 'sometimes|required|exists:projects,id',
        ]);
    
        // Cập nhật nhiệm vụ với dữ liệu mới
        $task->update($validatedData);
    
        return response()->json($task, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Tìm nhiệm vụ theo ID
        $task = Task::findOrFail($id);
        // Xóa nhiệm vụ
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully'], 200);
    }
}
