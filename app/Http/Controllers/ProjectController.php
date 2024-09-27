<?php

namespace App\Http\Controllers;
use App\Models\Project;

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
            'department_ids.*' => 'exists:departments,id', // Kiểm tra từng phần tử trong mảng
        ]);
    
        // Nếu department_ids được cung cấp, cập nhật các phòng ban liên kết
        if (isset($validatedData['department_ids'])) {
            // Cập nhật các phòng ban liên kết
            $project->departments()->sync($validatedData['department_ids']);
        }
    
        // Cập nhật các thông tin khác của dự án nếu được cung cấp
        $project->update($validatedData);
    
        // Trả về phản hồi JSON với thông tin dự án đã được cập nhật
        return response()->json($project->load('departments'), 200);
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
