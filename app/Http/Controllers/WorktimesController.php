<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorktimesRequest;
use App\Http\Requests\UpdateWorktimesRequest;
use App\Models\Worktimes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Models\Task;
use Illuminate\Support\Facades\DB;


class WorktimesController extends Controller
{

    public function index(Request $request)
    {
        // Kiểm tra quyền xem Worktimes thông qua Policy
        // $this->authorize('viewAny', Worktimes::class);
    
        // Truy vấn dữ liệu Worktimes theo quyền của user
        if ($request->user()->role_id === 1 || $request->user()->role_id === 2) {
            // Admin và Manager có thể xem tất cả Worktimes
            $worktimes = Worktimes::with('user')->get();
        } elseif ($request->user()->role_id === 3) {
            // Staff có thể xem Worktimes tùy thuộc vào các điều kiện
            if (!is_null($request->user()->create_by)) {
                // Nếu Staff có create_by, họ có thể xem các Worktime mà họ đã tạo
                $worktimes = Worktimes::where('user_id', $request->user()->id)->with('user')->get();
            } elseif (is_null($request->user()->create_by)) {
                // Lấy các phòng ban mà user thuộc về
                $userDepartments = $request->user()->departments()->pluck('department_id')->toArray();
                
                // Lấy các project mà các phòng ban này tham gia thông qua bảng project_department
                $projects = DB::table('project_department')
                    ->whereIn('department_id', $userDepartments)
                    ->pluck('project_id')->toArray();
            
                // Lấy các worktimes có project_id trong danh sách các project mà user tham gia
                $worktimes = Worktimes::whereIn('project_id', $projects)
                    ->with('user')  // Kết hợp thông tin người dùng
                    ->get();
            }
            
        } else {
            // Nếu không có quyền, trả về lỗi
            return response()->json(['error' => 'Unauthorized'], 403);
        }
    
        // Trả về kết quả sau khi đã phân quyền
        return response()->json($worktimes, 200);
    }
    

    public function store(StoreWorktimesRequest $request)
    {
        try {
            // Dữ liệu đã được xác thực bởi StoreWorktimesRequest
            $validatedData = $request->validated();

            // Thêm ID của người dùng đang đăng nhập vào dữ liệu đã xác thực
            $validatedData['user_id'] = Auth::id();

            // Tạo Worktime mới
            $worktime = Worktimes::create($validatedData);

            return response()->json([
                'message' => 'Worktime created successfully',
                'worktime' => $worktime,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Bắt lỗi xác thực và trả về thông báo lỗi
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Bắt lỗi chung và trả về thông báo lỗi
            return response()->json([
                'error' => 'Failed to create worktime: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $worktimes = Worktimes::with('user')->find($id);
        return response()->json($worktimes, 200);
    }

    public function update(UpdateWorktimesRequest $request, $id)
    {
        try {
            // Lấy dữ liệu đã xác thực từ UpdateWorktimesRequest
            $validatedData = $request->validated();

            // Tìm Worktime cần cập nhật
            $worktime = Worktimes::findOrFail($id);

            // Đảm bảo `user_id` không bị thay đổi bằng cách loại bỏ khỏi dữ liệu cập nhật
            unset($validatedData['user_id']);

            // Cập nhật Worktime với dữ liệu mới, ngoại trừ user_id
            $worktime->update($validatedData);

            return response()->json([
                'message' => 'Worktime updated successfully',
                'worktime' => $worktime,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Trả về lỗi nếu không tìm thấy Worktime
            return response()->json([
                'error' => 'Worktime not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Bắt lỗi xác thực và trả về thông báo lỗi
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Bắt lỗi chung và trả về thông báo lỗi
            return response()->json([
                'error' => 'Failed to update worktime: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Tìm assignment theo ID
            $worktime = Worktimes::findOrFail($id);
    
            // Phân quyền xóa phân công
            $this->authorize('delete', $worktime); // Truyền assignment vào để phân quyền
    
            // Thực hiện xóa mềm
            $worktime->delete();
    
            return response()->json(['message' => 'Xóa phân công mềm thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Xóa phân công thất bại: ' . $e->getMessage()], 500);
        }
    }

    // chỉ update riêng trường status
    public function updateStatus(Request $request, $id)
    {
        try {
            // Validate dữ liệu đầu vào
            $request->validate([
                'status' => 'sometimes|required|integer|in:1,2,3', // Các trạng thái hợp lệ
            ]);

            // Tìm worktime theo ID
            $worktime = Worktimes::findOrFail($id);

            // Lấy trạng thái mới và cũ
            $newStatus = $request->input('status');
            $oldStatus = $worktime->status;

            // Kiểm tra điều kiện: chỉ kiểm tra trạng thái của các task khi trạng thái mới là 3
            if ($newStatus == 3 && $oldStatus != 3) {
                // Kiểm tra tất cả các task có worktime_id là $id, và kiểm tra trạng thái của chúng
                $tasks = Task::where('worktime_id', $id)->get(); // Lấy tất cả các task có worktime_id là $id

                // Kiểm tra xem có task nào có trạng thái khác 'done' không
                foreach ($tasks as $task) {
                    if ($task->status !== 'done') {
                        // Nếu có task nào không phải 'done', không cho phép cập nhật trạng thái worktime
                        return response()->json([
                            'error' => 'You can only update the worktime status to 3 or complete when all tasks are done.',
                        ], 400);
                    }
                }
            }

            // Cập nhật trạng thái
            $worktime->update(['status' => $newStatus]);

            // Ghi lại lịch sử thay đổi trạng thái
            ActivityLog::create([
                'user_id' => Auth::user()->id, // ID của người thực hiện
                'loggable_id' => $worktime->id, // ID của worktime
                'loggable_type' => 'App\Models\Worktime', // Loại đối tượng
                'action' => 'updated', // Hành động cập nhật
                'changes' => json_encode([
                    'status' => [
                        'old' => $oldStatus,
                        'new' => $newStatus,
                    ],
                ]), // Ghi lại thay đổi trạng thái
            ]);

            // Trả về JSON response với worktime đã cập nhật
            return response()->json([
                'message' => 'Worktime status updated successfully!',
                'worktime' => $worktime,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation error.',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update task status: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function trashedWorktimes()
    {
        $trashedWorktimes = Worktimes::onlyTrashed()->get();
        return response()->json($trashedWorktimes);
    }
    public function forceDestroy($id)
    {
        try {
            // Tìm Worktime đã bị xóa mềm
            $worktime = Worktimes::onlyTrashed()->findOrFail($id);

            // Xóa vĩnh viễn (force delete)
            $worktime->forceDelete();

            return response()->json([
                'message' => 'Worktime permanently deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to permanently delete worktime: ' . $e->getMessage()
            ], 500);
        }
    }
}
