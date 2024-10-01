<?php

namespace App\Http\Controllers;
use App\Models\Project;
use App\Models\Department;


use Illuminate\Http\Request;

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
    public function store(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validatedData = $request->validate([
                'project_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'status' => 'required|integer|in:0,1,2', // Điều chỉnh xác thực cho status là số
                'user_id' => 'required|exists:users,id',
                'department_ids' => 'required|array', // Danh sách department_ids
                'department_ids.*' => 'exists:departments,id',
            ]);
    
            // Tạo dự án mới
            $project = Project::create($validatedData);
            $project->departments()->attach($request->department_ids);
            // Trả về phản hồi JSON
            return response()->json($project, 201);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Trả về lỗi xác thực chi tiết
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // Trả về lỗi khác
            return response()->json(['error' => $e->getMessage()], 500);
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
    public function update(Request $request, string $id)
    {
        // Tìm dự án cần cập nhật
        $project = Project::findOrFail($id);
    
        // Xác thực dữ liệu đầu vào, tất cả các trường là tùy chọn
        $validatedData = $request->validate([
            'project_name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'status' => 'sometimes|required|integer|in:0,1,2',
            'user_id' => 'sometimes|required|exists:users,id',
            'department_ids' => 'sometimes|array', // Nhận mảng các department_id nếu được cung cấp
            'department_ids.*' => 'integer', // Đảm bảo tất cả phần tử là số nguyên
        ]);
    
        try {
            // Nếu `department_ids` được cung cấp, kiểm tra tính hợp lệ của từng `department_id`
            if (isset($validatedData['department_ids'])) {
                // Lấy danh sách tất cả các `department_id` hợp lệ từ cơ sở dữ liệu
                $validDepartmentIds = Department::pluck('id')->toArray();
    
                // Kiểm tra xem các `department_ids` nhập vào có tồn tại trong danh sách không
                $invalidDepartments = array_diff($validatedData['department_ids'], $validDepartmentIds);
    
                // Nếu có bất kỳ `department_id` nào không tồn tại trong cơ sở dữ liệu, thông báo lỗi
                if (!empty($invalidDepartments)) {
                    return response()->json([
                        'error' => 'The following departments do not exist in the database: ' . implode(', ', $invalidDepartments)
                    ], 400);
                }
    
                // Cập nhật các phòng ban liên kết nếu tất cả `department_id` hợp lệ
                $project->departments()->sync($validatedData['department_ids']);
            }
    
            // Cập nhật các thông tin khác của dự án nếu được cung cấp
            $project->update($validatedData);
    
            // Trả về phản hồi JSON với thông tin dự án đã được cập nhật
            return response()->json(['message' => 'Project updated successfully', 'project' => $project->load('departments')], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update project: ' . $e->getMessage()], 500);
        }
    }
    
    
    
      
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $project = Project::find($id);
        if(!$project){
            return response()->json(['message' => 'Project not found'], 404);
        }
        $project->delete();
        return response()->json(['message' => 'Project deleted successfully']);
    }
}
