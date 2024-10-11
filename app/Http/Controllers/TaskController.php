<?php

namespace App\Http\Controllers;
use App\Models\Task;
use App\Models\Project;
use App\Models\Department;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Exception;

use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tasks = Task::with('projects', 'departments', 'files')->get();
        return response()->json($tasks, 200);
    }
    /**
     * Store a newly created resource in storage.
     */

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


    /**
     * Display the specified resource.
     */
    public function show($task_id)
    {
        // Tải trước mối quan hệ project và departments của project
        $task = Task::with('projects.departments', 'files')->find($task_id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        return response()->json($task, 200);
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, $task_id)
    {
        // Tìm task theo ID và lấy cả các projects liên kết
        $task = Task::with('projects', 'departments', 'files')->findOrFail($task_id);

        try {
            // Lấy dữ liệu đã xác thực từ `UpdateTaskRequest`
            $validatedData = $request->validated();

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
