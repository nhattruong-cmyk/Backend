<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorktimesRequest;
use App\Http\Requests\UpdateWorktimesRequest;
use App\Models\Worktimes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class WorktimesController extends Controller
{

    public function index()
    {
        $worktimes = Worktimes::with('user')->get();
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
            // Tìm Worktime theo ID
            $worktime = Worktimes::findOrFail($id);
    
            // Kiểm tra xem worktime có dính khóa ngoại với tasks hay không
            $tasksCount = $worktime->tasks()->count(); // Đếm số lượng tasks liên quan
    
            if ($tasksCount > 0) {
                return response()->json([
                    'error' => 'Cannot delete worktime because it is associated with tasks.'
                ], 400); // 400 Bad Request
            }
    
            // Xóa mềm (soft delete)
            $worktime->delete();
    
            return response()->json([
                'message' => 'Worktime soft deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Worktime not found.'
            ], 404); // 404 Not Found
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to soft delete worktime: ' . $e->getMessage()
            ], 500); // 500 Internal Server Error
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
