<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tasks = Task::with('projects')->get(); // Chú ý thay đổi từ 'project' thành 'projects'
        return response()->json($tasks);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'task_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'required|integer',
            'project_id' => 'required|exists:projects,id',
        ]);

        $task = Task::create($request->all());
        return response()->json(['message' => 'Task created successfully', 'task' => $task], 201);
    }
    // Thêm nhiều user vào task
    public function addUsers(Request $request, $task_id)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id', // Kiểm tra xem các user_id có tồn tại không
        ]);

        $task = Task::find($task_id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        // Liên kết các user với task bằng bảng phụ task_user
        $task->users()->syncWithoutDetaching($request->user_ids);

        return response()->json(['message' => 'Users added to task successfully']);
    }



    /**
     * Display the specified resource.
     */

    public function show($id)
    {
        $task = Task::with('projects')->find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        return response()->json($task);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $task->update($request->all());
        return response()->json(['message' => 'Task updated successfully', 'task' => $task]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $task->delete();
        return response()->json(['message' => 'Task deleted successfully']);
    }
}
