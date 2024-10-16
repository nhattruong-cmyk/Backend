<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Department;
use App\Http\Requests\StoreProjectRequest;
use Exception;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = Project::with(['user', 'departments'])->get();
        return response()->json($projects);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request) // Sử dụng StoreProjectRequest
    {
        try {
            // Lấy dữ liệu đã xác thực từ Request class
            $validatedData = $request->validated();

            // Tạo dự án mới với dữ liệu đã xác thực
            $project = Project::create($validatedData);

            // Ghi lại lịch sử hoạt động sau khi tạo project thành công
            ActivityLog::create([
                'user_id' => Auth::user()->id, // Người thực hiện hành động
                'loggable_id' => $project->id, // ID của dự án vừa được tạo
                'loggable_type' => 'App\Models\Project', // Loại đối tượng là Project
                'action' => 'created', // Hành động là tạo mới
                'changes' => json_encode($validatedData), // Lưu lại dữ liệu vừa được tạo
            ]);

            // Trả về phản hồi JSON với dữ liệu dự án đã tạo
            return response()->json([
                'message' => 'Project created successfully',
                'project' => $project,
            ], 201);
        } catch (Exception $e) {
            // Trả về lỗi bất thường
            return response()->json(['error' => 'Failed to create project: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $project = Project::with(['user', 'departments'])->findOrFail($id);
        return response()->json($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, string $id)
    {
        // Tìm dự án cần cập nhật
        $project = Project::findOrFail($id);

        try {
            // Lấy dữ liệu cũ để ghi lại sự thay đổi
            $originalData = $project->getOriginal();

            // Cập nhật các thông tin của dự án
            $project->update($request->validated());

            // Ghi lại lịch sử hoạt động sau khi cập nhật project thành công
            ActivityLog::create([
                'user_id' => Auth::user()->id, // Người thực hiện hành động
                'loggable_id' => $project->id, // ID của dự án vừa được cập nhật
                'loggable_type' => 'App\Models\Project', // Loại đối tượng là Project
                'action' => 'updated', // Hành động là cập nhật
                'changes' => json_encode([
                    'before' => $originalData, // Dữ liệu trước khi cập nhật
                    'after' => $project->getChanges() // Dữ liệu sau khi cập nhật
                ]),
            ]);

            // Nếu có `user_id` được cập nhật, gửi thông báo đến người dùng
            if ($request->has('user_id')) {
                $user = User::find($request->user_id);
                if ($user) {
                    // Tạo thông báo cho người dùng mới
                    Notification::create([
                        'user_id' => $user->id,
                        'message' => "You have been assigned to the project '{$project->project_name}'."
                    ]);
                }
            }

            // Trả về phản hồi JSON với thông tin dự án đã được cập nhật
            return response()->json([
                'message' => 'Project updated successfully',
                'project' => $project->load('departments')
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update project: ' . $e->getMessage()], 500);
        }
    }


    public function addDepartmentToProject(Request $request, string $project_id)
    {
        // Tìm dự án theo ID
        $project = Project::findOrFail($project_id);

        // Xác thực dữ liệu đầu vào
        $validatedData = $request->validate([
            'department_ids' => 'required', // Yêu cầu có `department_ids`
        ]);

        try {
            // Kiểm tra xem `department_ids` là mảng hay giá trị đơn
            if (!is_array($validatedData['department_ids'])) {
                // Nếu chỉ là một giá trị, chuyển thành mảng
                $validatedData['department_ids'] = [(int)$validatedData['department_ids']];
            }

            // Lấy danh sách `department_id` hợp lệ từ cơ sở dữ liệu
            $validDepartmentIds = Department::pluck('id')->toArray();

            // Kiểm tra tính hợp lệ của các `department_id` đã nhập
            $invalidDepartments = array_diff($validatedData['department_ids'], $validDepartmentIds);

            if (!empty($invalidDepartments)) {
                return response()->json([
                    'error' => 'The following departments do not exist in the database: ' . implode(', ', $invalidDepartments)
                ], 400);
            }

            // Thêm các phòng ban vào dự án (sử dụng `syncWithoutDetaching` để tránh mất các phòng ban đã có trước đó)
            $project->departments()->syncWithoutDetaching($validatedData['department_ids']);

            // Ghi lại lịch sử hoạt động sau khi thêm phòng ban
            ActivityLog::create([
                'user_id' => Auth::user()->id, // Người thực hiện hành động
                'loggable_id' => $project->id, // ID của dự án
                'loggable_type' => 'App\Models\Project', // Loại đối tượng (Project)
                'action' => 'added_department', // Hành động thêm phòng ban
                'changes' => json_encode(['added_departments' => $validatedData['department_ids']]), // Lưu danh sách các phòng ban được thêm
            ]);

            // Trả về phản hồi với thông tin dự án đã được cập nhật
            return response()->json([
                'message' => 'Departments added to project successfully',
                'project' => $project->load('departments')
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to add departments to project: ' . $e->getMessage()], 500);
        }
    }


    public function removeDepartmentFromProject(Request $request, string $project_id)
    {
        // Tìm dự án theo ID
        $project = Project::findOrFail($project_id);

        // Xác thực dữ liệu đầu vào, nhận `department_ids` có thể là mảng hoặc giá trị đơn
        $validatedData = $request->validate([
            'department_ids' => 'required', // Yêu cầu `department_ids` có mặt
        ]);

        try {
            // Kiểm tra xem `department_ids` là mảng hay giá trị đơn
            if (!is_array($validatedData['department_ids'])) {
                // Nếu chỉ là một giá trị, chuyển thành mảng để xử lý dễ dàng
                $validatedData['department_ids'] = [(int)$validatedData['department_ids']];
            }

            // Lấy danh sách phòng ban đang thuộc về project
            $currentDepartmentIds = $project->departments->pluck('id')->toArray();

            // Kiểm tra xem các phòng ban nhập vào có thuộc project hay không
            $invalidDepartments = array_diff($validatedData['department_ids'], $currentDepartmentIds);

            if (!empty($invalidDepartments)) {
                return response()->json([
                    'error' => 'The following departments are not part of this project: ' . implode(', ', $invalidDepartments)
                ], 400);
            }

            // Xóa các phòng ban được chỉ định ra khỏi project
            $project->departments()->detach($validatedData['department_ids']);

            // Ghi lại lịch sử hoạt động sau khi xóa phòng ban
            ActivityLog::create([
                'user_id' => Auth::user()->id, // Người thực hiện hành động
                'loggable_id' => $project->id, // ID của dự án
                'loggable_type' => 'App\Models\Project', // Loại đối tượng (Project)
                'action' => 'removed_department', // Hành động xóa phòng ban
                'changes' => json_encode(['removed_departments' => $validatedData['department_ids']]), // Lưu danh sách các phòng ban bị xóa
            ]);

            return response()->json([
                'message' => 'Departments removed from project successfully',
                'project' => $project->load('departments')
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to remove departments from project: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Tìm dự án theo ID
        $project = Project::find($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        try {
            // Lưu dữ liệu dự án trước khi xóa để ghi lại lịch sử
            $projectData = $project->toArray();

            // Xóa mềm dự án
            $project->delete();

            // Ghi lại lịch sử hoạt động sau khi xóa mềm dự án
            ActivityLog::create([
                'user_id' => Auth::user()->id, // Người thực hiện hành động
                'loggable_id' => $id, // ID của dự án vừa bị xóa
                'loggable_type' => 'App\Models\Project', // Loại đối tượng là Project
                'action' => 'soft_deleted', // Hành động xóa mềm dự án
                'changes' => json_encode($projectData), // Lưu lại dữ liệu của dự án bị xóa
            ]);

            return response()->json(['message' => 'Project soft deleted successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to soft delete project: ' . $e->getMessage()], 500);
        }
    }



}
