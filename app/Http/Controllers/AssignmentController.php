<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\User;
use App\Models\Task;

use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index()
    {
        $assignments = Assignment::with('user','department','task')->get();
        return response()->json($assignments);
    }

    public function store(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $validatedData = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'user_ids' => 'required|array', // Chấp nhận mảng user_ids
            'user_ids.*' => 'integer', // Mỗi user_id phải là số nguyên (kiểm tra cơ bản)
            'department_id' => 'required|exists:departments,id',
        ]);
    
        try {
            // Kiểm tra department có thuộc task không
            $task = Task::with('departments')->findOrFail($validatedData['task_id']);
            if (!$task->departments->contains($validatedData['department_id'])) {
                return response()->json(['error' => 'The department is not part of the task'], 400);
            }
    
            // Kiểm tra `user_ids` không tồn tại trong bảng users
            $invalidUsers = [];
            $validUsers = User::whereIn('id', $validatedData['user_ids'])->pluck('id')->toArray();
    
            foreach ($validatedData['user_ids'] as $user_id) {
                if (!in_array($user_id, $validUsers)) {
                    $invalidUsers[] = $user_id;
                }
            }
    
            // Trả về lỗi nếu có `user_ids` không tồn tại trong bảng users
            if (!empty($invalidUsers)) {
                return response()->json([
                    'error' => 'The following user IDs do not exist in the database: ' . implode(', ', $invalidUsers)
                ], 400);
            }
    
            // Lấy danh sách người dùng có trong phòng ban cụ thể
            $validUsersInDepartment = DB::table('department_user')
                ->where('department_id', $validatedData['department_id'])
                ->pluck('user_id')
                ->toArray();
    
            // Lưu danh sách user không thuộc phòng ban và trùng lặp
            $invalidDepartmentUsers = [];
            $duplicateUsers = [];
    
            // Lặp qua danh sách user_ids và kiểm tra trùng lặp, người dùng không hợp lệ trước khi tạo assignment
            foreach ($validatedData['user_ids'] as $user_id) {
                // Kiểm tra nếu người dùng không thuộc phòng ban
                if (!in_array($user_id, $validUsersInDepartment)) {
                    $invalidDepartmentUsers[] = $user_id;
                    continue;
                }
    
                // Kiểm tra trùng lặp assignment
                $existingAssignment = Assignment::where('task_id', $validatedData['task_id'])
                    ->where('user_id', $user_id)
                    ->where('department_id', $validatedData['department_id'])
                    ->first();
    
                if ($existingAssignment) {
                    // Thêm user bị trùng vào danh sách
                    $duplicateUsers[] = $user_id;
                } else {
                    // Tạo assignment mới nếu chưa tồn tại và hợp lệ
                    Assignment::create([
                        'task_id' => $validatedData['task_id'],
                        'user_id' => $user_id,
                        'department_id' => $validatedData['department_id']
                    ]);
                }
            }
    
            // Trả về lỗi nếu có user không thuộc phòng ban hoặc trùng lặp
            if (!empty($invalidDepartmentUsers) || !empty($duplicateUsers)) {
                $errorMessages = [];
    
                // Thông báo lỗi cho các user không thuộc phòng ban
                if (!empty($invalidDepartmentUsers)) {
                    $invalidUserNames = User::whereIn('id', $invalidDepartmentUsers)->pluck('name')->toArray();
                    $errorMessages[] = 'The following users are not part of the department: ' . implode(', ', $invalidUserNames);
                }
    
                // Thông báo lỗi cho các user trùng lặp
                if (!empty($duplicateUsers)) {
                    $duplicateUserNames = User::whereIn('id', $duplicateUsers)->pluck('name')->toArray();
                    $errorMessages[] = 'The following users are already assigned to this task: ' . implode(', ', $duplicateUserNames);
                }
    
                return response()->json(['error' => implode(' | ', $errorMessages)], 400);
            }
    
            return response()->json(['message' => 'Users assigned to task successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to assign users to task: ' . $e->getMessage()], 500);
        }
    }
    
    
    
    
    public function update(Request $request, $id)
    {
        // Xác thực dữ liệu đầu vào với đôi khi (sometimes) xác thực
        $request->validate([
            'status' => 'sometimes|integer|in:0,1,2',
            'user_id' => 'sometimes|exists:users,id',
            'department_id' => 'sometimes|exists:departments,id',
        ]);
    
        try {
            // Tìm assignment theo ID
            $assignment = Assignment::findOrFail($id);
    
            // Nếu muốn cập nhật user hoặc department, cần kiểm tra lại
            if ($request->has('user_id') || $request->has('department_id')) {
                $newUserId = $request->input('user_id', $assignment->user_id);
                $newDepartmentId = $request->input('department_id', $assignment->department_id);
    
                // Tìm phòng ban và người dùng mới
                $department = Department::findOrFail($newDepartmentId);
                $user = User::findOrFail($newUserId);
    
                // Kiểm tra xem người dùng đã thuộc phòng ban chưa
                if (!$department->users->contains($user->id)) {
                    return response()->json(['error' => 'User does not belong to the specified department.'], 400);
                }
    
                // Cập nhật user và department
                $assignment->update([
                    'user_id' => $newUserId,
                    'department_id' => $newDepartmentId,
                ]);
            }
    
            // Cập nhật trạng thái nếu có
            if ($request->has('status')) {
                $assignment->status = $request->status;
                $assignment->save();
            }
    
            return response()->json(['message' => 'Assignment updated successfully', 'assignment' => $assignment], 200);
    
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update assignment: ' . $e->getMessage()], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $assignment = Assignment::findOrFail($id);
            $assignment->delete();

            return response()->json(['message' => 'Assignment deleted successfully'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete assignment: ' . $e->getMessage()], 500);
        }
    }

}
