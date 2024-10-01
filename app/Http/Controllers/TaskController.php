<?php

namespace App\Http\Controllers;
use App\Models\Task;
use App\Models\Project;
use App\Models\Department;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tasks = Task::with('project', 'departments', 'files')->get();
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
            'department_id' => 'required|integer',  // Không dùng exists ở đây để kiểm tra bằng tay
            'files.*' => 'nullable|file|mimes:jpg,png,pdf,doc,docx,zip|max:20480', // Định dạng file
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
                        'uploaded_by' => Auth::user()->id,
                    ]);
                }
            }

            // Trả về kết quả với thông tin task và các file liên quan
            return response()->json([
                'message' => 'Task created successfully!',
                'task' => $task->load('departments', 'files')  // Load thêm department và files liên quan
            ], 201);

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
        $task = Task::with('project.departments', 'files')->find($task_id);
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
        // Tìm task theo ID và lấy cả project cùng các departments liên kết với task đó
        $task = Task::with('project', 'files')->findOrFail($task_id);

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
            'status' => 'sometimes|required|integer|in:1,2,3', // Chỉ cập nhật nếu có trong request
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_id' => 'sometimes|required|exists:projects,id',
            'department_id' => 'sometimes|integer', // Xác thực department_id nếu có cung cấp
            'files.*' => 'nullable|file|mimes:jpg,png,pdf,doc,docx,zip|max:20480', // Định dạng file
            'delete_file_ids' => 'nullable|array',  // Xác định file cần xóa
            'delete_file_ids.*' => 'exists:files,id',  // Kiểm tra ID của file cần xóa có tồn tại
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

                // Cập nhật liên kết giữa task và department trong bảng pivot `task_department`
                $task->departments()->sync([$departmentId]);
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
                        'uploaded_by' => Auth::user()->id,
                    ]);
                }
            }

            // Trả về task đã cập nhật, kèm theo department và files liên quan
            return response()->json([
                'message' => 'Task updated successfully!',
                'task' => $task->load('departments', 'files')
            ], 200);

        } catch (\Exception $e) {
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
