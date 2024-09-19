<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = Project::with('tasks', 'department')->get();
        return response()->json($projects);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        // Lấy người dùng hiện tại
        $user = $request->user();


        // Quyền hạn khi tạo dự án
        if ($user->role_id == 3) { // Staff
            return response()->json(['message' => 'Unauthorized'], 403); // Không được phép tạo dự án
        }

        // Xử lý user_id dựa trên vai trò của người dùng
        $user_id = null;
        if ($user->role_id == 1 || $user->role_id == 2) { // Nếu là Admin (role_id = 1) hoặc Manager (role_id = 2)
            $user_id = $user->id;
        }


        // Tạo dự án mới
        $project = Project::create(array_merge(
            $request->except('user_id'), // Không truyền user_id từ request
            ['user_id' => $user_id] // Thêm user_id nếu có
        ));

        return response()->json(['message' => 'Project created successfully', 'project' => $project], 201);
    }

    /**
     * Display the specified resource.
     */
    public function addTasks(Request $request, $project_id)
    {
        $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id', // Kiểm tra các task_id có tồn tại trong bảng tasks
        ]);

        $project = Project::find($project_id);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        // Liên kết các task với project bằng bảng phụ project_task
        $project->tasks()->syncWithoutDetaching($request->task_ids);

        return response()->json(['message' => 'Tasks added to project successfully']);
    }


    public function show($id)
    {
        $project = Project::with('tasks')->find($id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        return response()->json($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'project_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'department_id' => 'nullable|exists:departments,id',
            'user_id' => 'nullable|exists:users,id', // Cho phép chỉnh sửa user_id nếu cần
        ]);

        // Cập nhật dự án
        $project->update($request->all());

        return response()->json(['message' => 'Project updated successfully', 'project' => $project]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $project->delete();
        return response()->json(['message' => 'Project deleted successfully']);
    }
}
